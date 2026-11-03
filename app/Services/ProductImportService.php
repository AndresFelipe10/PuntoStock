<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class ProductImportService
{
    /**
     * Asegura la existencia física de la plantilla oficial según el tipo de negocio.
     * Si no existe en public/templates/, la genera al vuelo con 2 hojas (Datos + Instrucciones).
     */
    public function ensureTemplateExists(string $type = 'retail'): string
    {
        $templatesDir = public_path('templates');
        if (! is_dir($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }

        $filename = $type === 'restaurant'
            ? 'Plantilla_Restaurante_PuntoStock.xlsx'
            : 'Plantilla_Comercio_Retail_PuntoStock.xlsx';

        $fullPath = $templatesDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($fullPath) && filesize($fullPath) > 0) {
            return $fullPath;
        }

        $this->generateTemplate($type, $fullPath);

        return $fullPath;
    }

    /**
     * Resuelve la ruta y nombre de la plantilla según el negocio o tipo especificado.
     *
     * @return array{path: string, filename: string}
     */
    public function getTemplateForBusiness(?Business $business, ?string $requestedType = null): array
    {
        $type = 'retail';
        $businessType = strtolower($business->business_type ?? $business->category ?? '');

        if (str_contains($businessType, 'restaurant') || str_contains($businessType, 'gastronomic') || $requestedType === 'restaurant') {
            $type = 'restaurant';
        }

        if ($requestedType === 'retail' || $requestedType === 'commerce') {
            $type = 'retail';
        }

        $path = $this->ensureTemplateExists($type);
        $filename = basename($path);

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * Procesa la importación masiva de productos e inventario desde un archivo Excel o CSV.
     *
     * @return array{
     *     created_count: int,
     *     updated_count: int,
     *     skipped_count: int,
     *     errors: array<int, string>,
     *     total_rows_processed: int
     * }
     */
    public function import(UploadedFile|string $file, Business $business, int $userId, bool $updateExisting = true): array
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($realPath);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $rows = $this->readRowsFromFile($realPath, $extension);

        if (empty($rows)) {
            return [
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'errors' => ['El archivo cargado está vacío o no contiene filas procesables.'],
                'total_rows_processed' => 0,
            ];
        }

        // 1. Detectar fila de encabezados en las primeras 10 filas
        $headerInfo = $this->detectHeaderRow($rows);
        if (! $headerInfo) {
            return [
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'errors' => ['No se encontraron los encabezados requeridos. Verifique que el archivo contenga la columna "Nombre del Producto" o "Nombre del Plato".'],
                'total_rows_processed' => 0,
            ];
        }

        $headerRowIndex = $headerInfo['row_index'];
        $columnMap = $headerInfo['column_map']; // ['name' => 0, 'sale_price' => 2, ...]

        // 2. Caché en memoria de categorías y productos existentes para el negocio
        $categoriesCache = Category::where('business_id', $business->id)
            ->get()
            ->keyBy(fn ($c) => mb_strtolower(trim($c->name)));

        $existingProductsBySku = Product::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereNotNull('sku')
            ->get()
            ->keyBy(fn ($p) => mb_strtolower(trim($p->sku)));

        $existingProductsByName = Product::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->get()
            ->keyBy(fn ($p) => mb_strtolower(trim($p->name)));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $isRestaurant = $business->isRestaurant();

        // 3. Procesar filas a partir de la fila siguiente a los encabezados
        $totalRows = count($rows);
        for ($i = $headerRowIndex + 1; $i < $totalRows; $i++) {
            $row = $rows[$i];
            $visualRowNumber = $i + 1;

            // Ignorar fila si está completamente vacía
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $rawName = isset($columnMap['name']) ? ($row[$columnMap['name']] ?? null) : null;
            $rawName = trim((string) $rawName);

            if ($rawName === '') {
                $errors[] = "Fila {$visualRowNumber}: El nombre del producto es obligatorio.";
                continue;
            }

            // Validar Precio de Venta
            $rawSalePrice = isset($columnMap['sale_price']) ? ($row[$columnMap['sale_price']] ?? null) : null;
            $parsedSalePrice = $this->parseCopCurrency($rawSalePrice);

            if ($parsedSalePrice === null || $parsedSalePrice < 0) {
                $errors[] = "Fila {$visualRowNumber}: El Precio de Venta es obligatorio y debe ser mayor o igual a cero.";
                continue;
            }

            // Validar Precio de Compra / Costo
            $rawPurchasePrice = isset($columnMap['purchase_price']) ? ($row[$columnMap['purchase_price']] ?? null) : null;
            $parsedPurchasePrice = $this->parseCopCurrency($rawPurchasePrice) ?? 0.00;

            if ($parsedPurchasePrice < 0) {
                $errors[] = "Fila {$visualRowNumber}: El Precio de Compra / Costo no puede ser negativo.";
                continue;
            }

            // Modalidad / product_type
            $rawModality = isset($columnMap['modality']) ? ($row[$columnMap['modality']] ?? null) : null;
            $productType = $this->resolveProductType($isRestaurant, $rawModality, $row, $columnMap);

            // Stock y Stock Mínimo
            if ($productType === 'dish') {
                $stock = 0.0;
                $minStock = 0.0;
            } else {
                $rawStock = isset($columnMap['stock']) ? ($row[$columnMap['stock']] ?? null) : null;
                $rawMinStock = isset($columnMap['min_stock']) ? ($row[$columnMap['min_stock']] ?? null) : null;
                $stock = $this->parseStock($rawStock) ?? 0.0;
                $minStock = $this->parseStock($rawMinStock) ?? 0.0;
            }

            // Categoría
            $rawCategory = isset($columnMap['category']) ? ($row[$columnMap['category']] ?? null) : null;
            $categoryName = trim((string) $rawCategory);
            $categoryId = null;

            if ($categoryName !== '') {
                $catKey = mb_strtolower($categoryName);
                if (isset($categoriesCache[$catKey])) {
                    $categoryId = $categoriesCache[$catKey]->id;
                } else {
                    $newCategory = Category::create([
                        'business_id' => $business->id,
                        'name' => $categoryName,
                        'is_active' => true,
                    ]);
                    $categoriesCache[$catKey] = $newCategory;
                    $categoryId = $newCategory->id;
                }
            } else {
                // Fallback categoría "General"
                $generalKey = 'general';
                if (isset($categoriesCache[$generalKey])) {
                    $categoryId = $categoriesCache[$generalKey]->id;
                } else {
                    $genCat = Category::firstOrCreate(
                        ['business_id' => $business->id, 'name' => 'General'],
                        ['is_active' => true]
                    );
                    $categoriesCache[$generalKey] = $genCat;
                    $categoryId = $genCat->id;
                }
            }

            // SKU y Descripción
            $rawSku = isset($columnMap['sku']) ? ($row[$columnMap['sku']] ?? null) : null;
            $sku = trim((string) $rawSku);
            $sku = $sku !== '' ? $sku : null;

            $rawDesc = isset($columnMap['description']) ? ($row[$columnMap['description']] ?? null) : null;
            $description = trim((string) $rawDesc);
            $description = $description !== '' ? $description : null;

            // 4. Buscar coincidencia por SKU o por Nombre
            $existingProduct = null;
            if ($sku !== null && isset($existingProductsBySku[mb_strtolower($sku)])) {
                $existingProduct = $existingProductsBySku[mb_strtolower($sku)];
            } elseif (isset($existingProductsByName[mb_strtolower($rawName)])) {
                $existingProduct = $existingProductsByName[mb_strtolower($rawName)];
            }

            try {
                DB::transaction(function () use (
                    $existingProduct,
                    $updateExisting,
                    $business,
                    $userId,
                    $categoryId,
                    $rawName,
                    $productType,
                    $sku,
                    $parsedSalePrice,
                    $parsedPurchasePrice,
                    $stock,
                    $minStock,
                    $description,
                    &$created,
                    &$updated,
                    &$skipped,
                    &$existingProductsBySku,
                    &$existingProductsByName
                ) {
                    if ($existingProduct) {
                        if ($updateExisting) {
                            $existingProduct->sale_price = $parsedSalePrice;
                            $existingProduct->cost_price = $parsedPurchasePrice;
                            if ($categoryId) {
                                $existingProduct->category_id = $categoryId;
                            }
                            if ($description !== null) {
                                $existingProduct->description = $description;
                            }
                            if ($productType) {
                                $existingProduct->product_type = $productType;
                            }
                            if ($existingProduct->isStandard()) {
                                $existingProduct->stock = $stock;
                                $existingProduct->min_stock = $minStock;
                            }
                            $existingProduct->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        // Producto nuevo
                        $finalSku = $sku ?: Product::generateUniqueSku($business->id, $productType);

                        $newProduct = Product::create([
                            'business_id' => $business->id,
                            'category_id' => $categoryId,
                            'name' => $rawName,
                            'product_type' => $productType,
                            'sku' => $finalSku,
                            'sale_price' => $parsedSalePrice,
                            'cost_price' => $parsedPurchasePrice,
                            'stock' => $stock,
                            'min_stock' => $minStock,
                            'description' => $description,
                            'is_active' => true,
                        ]);

                        if ($newProduct->isStandard() && $stock > 0) {
                            InventoryMovement::create([
                                'business_id' => $business->id,
                                'product_id' => $newProduct->id,
                                'user_id' => $userId,
                                'type' => InventoryMovement::TYPE_ENTRY,
                                'quantity' => $stock,
                                'previous_stock' => 0,
                                'new_stock' => $stock,
                                'reason' => 'Carga inicial masiva de inventario',
                                'movement_date' => now(),
                            ]);
                        }

                        // Actualizar cachés en memoria
                        $existingProductsBySku[mb_strtolower($finalSku)] = $newProduct;
                        $existingProductsByName[mb_strtolower($rawName)] = $newProduct;

                        $created++;
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = "Fila {$visualRowNumber}: Error al procesar '{$rawName}': " . $e->getMessage();
            }
        }

        return [
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'errors' => $errors,
            'total_rows_processed' => $created + $updated + $skipped + count($errors),
        ];
    }

    /**
     * Limpia y normaliza montos en pesos colombianos (COP).
     * Soporta enteros, decimales y strings formateados ("$ 25.000", "25,000.00", "25.000").
     */
    public function parseCopCurrency(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $str = trim((string) $value);
        $str = preg_replace('/[^\d.,]/', '', $str);

        if ($str === '') {
            return null;
        }

        // Si contiene punto y coma al tiempo (ej: 25.000,50 o 25,000.50)
        if (str_contains($str, '.') && str_contains($str, ',')) {
            $lastDot = strrpos($str, '.');
            $lastComma = strrpos($str, ',');
            if ($lastComma > $lastDot) {
                // Formato latino: 25.000,50 -> 25000.50
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // Formato anglosajón: 25,000.50 -> 25000.50
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, '.')) {
            // Solo tiene punto
            $parts = explode('.', $str);
            if (count($parts) > 2) {
                // Múltiples puntos: miles (ej: 1.250.000 -> 1250000)
                $str = str_replace('.', '', $str);
            } elseif (count($parts) === 2) {
                if (strlen($parts[1]) === 3) {
                    // Exactamente 3 dígitos tras el punto en COP son miles (ej: 25.000 -> 25000)
                    $str = str_replace('.', '', $str);
                }
            }
        } elseif (str_contains($str, ',')) {
            // Solo tiene coma
            $parts = explode(',', $str);
            if (count($parts) > 2) {
                $str = str_replace(',', '', $str);
            } elseif (count($parts) === 2) {
                if (strlen($parts[1]) === 3) {
                    // Miles con coma (ej: 25,000 -> 25000)
                    $str = str_replace(',', '', $str);
                } else {
                    // Decimal con coma (ej: 25,50 -> 25.50)
                    $str = str_replace(',', '.', $str);
                }
            }
        }

        return is_numeric($str) ? round((float) $str, 2) : null;
    }

    /**
     * Limpia y normaliza cantidades de inventario (enteros o decimales hasta 3 dígitos).
     */
    public function parseStock(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 3);
        }

        $str = trim((string) $value);
        $str = preg_replace('/[^\d.,]/', '', $str);

        if ($str === '') {
            return null;
        }

        $str = str_replace(',', '.', $str);

        return is_numeric($str) ? round((float) $str, 3) : null;
    }

    /**
     * Resuelve el tipo de producto según el giro y la modalidad leída.
     */
    protected function resolveProductType(bool $isRestaurant, mixed $rawModality, array $row, array $columnMap): string
    {
        if (! $isRestaurant) {
            return 'standard';
        }

        $modalityStr = mb_strtolower(trim((string) $rawModality));

        if ($modalityStr !== '') {
            if (preg_match('/(carta|menu|menú|cocina|plato)/iu', $modalityStr)) {
                return 'dish';
            }
            if (preg_match('/(mercanc|mostrador|inventario|bebida|producto)/iu', $modalityStr)) {
                return 'standard';
            }
        }

        // Si no viene modalidad especificada en restaurante:
        $rawStock = isset($columnMap['stock']) ? ($row[$columnMap['stock']] ?? null) : null;
        $stock = $this->parseStock($rawStock) ?? 0.0;

        // Si tiene stock asignado > 0 asumimos mercancía de mostrador, de lo contrario plato de carta
        return $stock > 0 ? 'standard' : 'dish';
    }

    /**
     * Lee todas las filas de la primera hoja del archivo Excel o CSV.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function readRowsFromFile(string $filePath, string $extension): array
    {
        if ($extension === 'csv' || $extension === 'txt') {
            $reader = new CsvReader();
            $reader->setDelimiter($this->detectCsvDelimiter($filePath));
            $reader->setInputEncoding('UTF-8');
            $spreadsheet = $reader->load($filePath);
        } else {
            $spreadsheet = IOFactory::load($filePath);
        }

        // Procesar estrictamente la primera hoja
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, false, false);
    }

    /**
     * Detecta el delimitador de un archivo CSV analizando su primera línea.
     */
    protected function detectCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ',';
        }

        $line = fgets($handle);
        fclose($handle);

        if (! $line) {
            return ',';
        }

        $semicolons = substr_count($line, ';');
        $commas = substr_count($line, ',');
        $tabs = substr_count($line, "\t");

        if ($semicolons > $commas && $semicolons > $tabs) {
            return ';';
        }
        if ($tabs > $commas && $tabs > $semicolons) {
            return "\t";
        }

        return ',';
    }

    /**
     * Inspecciona las primeras 10 filas para localizar la fila de encabezados.
     *
     * @return array{row_index: int, column_map: array<string, int>}|null
     */
    protected function detectHeaderRow(array $rows): ?array
    {
        $maxScan = min(10, count($rows));

        for ($r = 0; $r < $maxScan; $r++) {
            $row = $rows[$r];
            $map = [];

            foreach ($row as $colIdx => $cellVal) {
                if ($cellVal === null || $cellVal === '') {
                    continue;
                }

                $key = $this->normalizeHeader(strval($cellVal));
                if ($key && ! isset($map[$key])) {
                    $map[$key] = $colIdx;
                }
            }

            // Si contiene al menos el nombre y el precio de venta (o categoría), es la fila de encabezados
            if (isset($map['name']) && (isset($map['sale_price']) || isset($map['category']))) {
                return [
                    'row_index' => $r,
                    'column_map' => $map,
                ];
            }
        }

        return null;
    }

    /**
     * Normaliza el texto de un encabezado a una clave canónica.
     */
    protected function normalizeHeader(string $rawHeader): ?string
    {
        $clean = mb_strtolower(trim($rawHeader));
        $clean = strtr($clean, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            '*' => '', '/' => ' ', '-' => ' ', '_' => ' ',
        ]);
        $clean = preg_replace('/\s+/', ' ', $clean);

        if (str_contains($clean, 'nombre del producto') || str_contains($clean, 'nombre del plato') || $clean === 'nombre' || $clean === 'producto' || $clean === 'plato') {
            return 'name';
        }

        if (str_contains($clean, 'modalidad')) {
            return 'modality';
        }

        if (str_contains($clean, 'categoria')) {
            return 'category';
        }

        if (str_contains($clean, 'precio de venta') || str_contains($clean, 'precio venta') || $clean === 'pvp' || $clean === 'precio') {
            return 'sale_price';
        }

        if (str_contains($clean, 'compra') || str_contains($clean, 'costo') || str_contains($clean, 'cost_price')) {
            return 'purchase_price';
        }

        if (str_contains($clean, 'minimo') || str_contains($clean, 'min stock') || str_contains($clean, 'stock min')) {
            return 'min_stock';
        }

        if (str_contains($clean, 'stock inicial') || $clean === 'stock' || str_contains($clean, 'existencias') || str_contains($clean, 'cantidad')) {
            return 'stock';
        }

        if (str_contains($clean, 'codigo') || str_contains($clean, 'sku')) {
            return 'sku';
        }

        if (str_contains($clean, 'descripcion') || str_contains($clean, 'acompan') || str_contains($clean, 'notas') || str_contains($clean, 'detalle')) {
            return 'description';
        }

        return null;
    }

    /**
     * Verifica si una fila está completamente vacía.
     */
    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Genera una plantilla Excel oficial (.xlsx) de 2 hojas para Retail o Restaurante.
     */
    protected function generateTemplate(string $type, string $destPath): void
    {
        $spreadsheet = new Spreadsheet();

        if ($type === 'restaurant') {
            // Hoja 1: Menu_y_Productos
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Menu_y_Productos');

            $sheet1->setCellValue('A1', 'PUNTOSTOCK — PLANTILLA OFICIAL DE MENÚ Y PRODUCTOS (RESTAURANTES Y GASTROBARES)');
            $sheet1->setCellValue('A2', "Diligencie sus platos y bebidas desde la fila 5. Use Modalidad 'Carta' para platos preparados en cocina y 'Mercancía' para bebidas/empaquetados con stock.");

            $headers = [
                'A4' => 'Nombre del Plato o Producto *',
                'B4' => 'Modalidad *',
                'C4' => 'Categoría *',
                'D4' => 'Precio de Venta *',
                'E4' => 'Precio de Compra / Costo',
                'F4' => 'Stock Inicial',
                'G4' => 'Stock Mínimo',
                'H4' => 'Código / SKU',
                'I4' => 'Descripción / Acompañamientos',
            ];
            foreach ($headers as $cell => $val) {
                $sheet1->setCellValue($cell, $val);
            }

            // Ejemplos
            $sheet1->setCellValue('A5', 'Pollo Asado Completo');
            $sheet1->setCellValue('B5', 'Carta');
            $sheet1->setCellValue('C5', 'Platos Fuertes');
            $sheet1->setCellValue('D5', 25000);
            $sheet1->setCellValue('H5', 'MNU-001');
            $sheet1->setCellValue('I5', 'Acompañado de papas a la francesa, patacón y ensalada');

            $sheet1->setCellValue('A6', 'Gaseosa 400ml');
            $sheet1->setCellValue('B6', 'Mercancía');
            $sheet1->setCellValue('C6', 'Bebidas');
            $sheet1->setCellValue('D6', 4500);
            $sheet1->setCellValue('E6', 2500);
            $sheet1->setCellValue('F6', 48);
            $sheet1->setCellValue('G6', 12);
            $sheet1->setCellValue('H6', 'BEB-001');
            $sheet1->setCellValue('I6', 'Botella no retornable');

            // Hoja 2: Instrucciones_Restaurante
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Instrucciones_Restaurante');
            $sheet2->setCellValue('A1', 'INSTRUCCIONES DE LLENADO — RESTAURANTES Y GASTROBARES');
            $sheet2->setCellValue('A3', '1. Modalidad: "Carta" para preparaciones de cocina sin control de existencias físicas.');
            $sheet2->setCellValue('A4', '2. Modalidad: "Mercancía" para bebidas, snacks o productos con control de stock.');
            $sheet2->setCellValue('A5', '3. Los precios deben ingresarse en números sin decimales innecesarios (ej: 25000).');
        } else {
            // Hoja 1: Inventario_Retail
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Inventario_Retail');

            $sheet1->setCellValue('A1', 'PUNTOSTOCK — PLANTILLA OFICIAL DE PRODUCTOS E INVENTARIO (COMERCIO RETAIL)');
            $sheet1->setCellValue('A2', 'Diligencie sus productos a partir de la fila 5. No modifique los encabezados de la fila 4. Los campos con (*) son obligatorios.');

            $headers = [
                'A4' => 'Nombre del Producto *',
                'B4' => 'Categoría *',
                'C4' => 'Precio de Venta *',
                'D4' => 'Precio de Compra / Costo',
                'E4' => 'Stock Inicial *',
                'F4' => 'Stock Mínimo',
                'G4' => 'Código / SKU',
                'H4' => 'Descripción / Notas',
            ];
            foreach ($headers as $cell => $val) {
                $sheet1->setCellValue($cell, $val);
            }

            // Ejemplos
            $sheet1->setCellValue('A5', 'Proteína Whey Gold 2lb');
            $sheet1->setCellValue('B5', 'Suplementos');
            $sheet1->setCellValue('D5', 85000);
            $sheet1->setCellValue('C5', 130000);
            $sheet1->setCellValue('E5', 15);
            $sheet1->setCellValue('F5', 3);
            $sheet1->setCellValue('G5', 'SUP-001');
            $sheet1->setCellValue('H5', 'Sabor Vainilla presentación tarro');

            // Hoja 2: Instrucciones_Retail
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Instrucciones_Retail');
            $sheet2->setCellValue('A1', 'INSTRUCCIONES DE LLENADO — COMERCIO RETAIL');
            $sheet2->setCellValue('A3', '1. Diligencie Nombre, Categoría, Precio de Venta y Stock Inicial como campos obligatorios.');
            $sheet2->setCellValue('A4', '2. Si deja el Código / SKU vacío, PuntoStock generará automáticamente un código consecutivo ART-XXX.');
        }

        // Estilos básicos en Fila 4 de la primera hoja
        $headerRange = $type === 'restaurant' ? 'A4:I4' : 'A4:H4';
        $sheet1->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet1->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B2A49');

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($destPath);
    }
}

