<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\StoreTenantUserRequest;
use App\Mail\WelcomeTenantMail;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Display a listing of businesses with search and status filtering.
     */
    public function index(Request $request): View
    {
        $query = Business::with(['users' => function ($q) {
            $q->wherePivot('is_active', true);
        }]);

        if ($search = $request->input('search')) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('nit', 'like', "%{$escaped}%")
                  ->orWhere('phone', 'like', "%{$escaped}%")
                  ->orWhere('email', 'like', "%{$escaped}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $businesses = $query->latest()->paginate(15)->withQueryString();

        return view('superadmin.businesses.index', [
            'businesses' => $businesses,
            'search' => $search,
            'selectedStatus' => $request->input('status'),
        ]);
    }

    /**
     * Show the form for creating a new business + initial administrator.
     */
    public function create(): View
    {
        return view('superadmin.businesses.create');
    }

    /**
     * Store a newly created business and its administrator transactionally.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->has('phone') && $request->filled('phone')) {
            $rawPhone = trim((string)$request->input('phone'));
            if (!preg_match('/[a-zA-Z]/', $rawPhone)) {
                $request->merge(['phone' => preg_replace('/[\s\-()]/', '', $rawPhone)]);
            }
        }
        if ($request->has('nit') && trim((string)$request->input('nit')) === '') {
            $request->merge(['nit' => null]);
        }
        if ($request->has('address') && trim((string)$request->input('address')) === '') {
            $request->merge(['address' => null]);
        }
        if ($request->has('email') && trim((string)$request->input('email')) === '') {
            $request->merge(['email' => null]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'business_type' => ['nullable', 'string', 'in:retail,restaurant'],
            'nit' => ['nullable', 'string', 'min:5', 'max:15', 'regex:/^\d{5,15}(-\d)?$/'],
            'phone' => ['nullable', 'string', 'min:7', 'max:15', 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => [
                'nullable',
                'string',
                'min:5',
                'max:150',
                'regex:/^(?!.*(.)\1{5,})[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s#\-\.,\/°]+$/u',
            ],
            'admin_name' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.\'-]+$/u',
            ],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['nullable', 'string', 'min:8'],
            'nightly_report_enabled' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre del negocio es obligatorio.',
            'name.min' => 'El nombre del negocio debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre del negocio no puede superar los 100 caracteres.',
            'nit.min' => 'El NIT debe tener al menos 5 dígitos.',
            'nit.max' => 'El NIT no puede superar los 15 caracteres.',
            'nit.regex' => 'El NIT debe contener entre 5 y 15 dígitos numéricos (con guion opcional).',
            'phone.min' => 'El teléfono debe tener al menos 7 dígitos.',
            'phone.max' => 'El teléfono no puede superar los 15 caracteres.',
            'phone.regex' => 'El teléfono debe contener únicamente números (entre 7 y 15 dígitos) y opcionalmente el prefijo +.',
            'address.min' => 'La dirección debe tener al menos 5 caracteres.',
            'address.max' => 'La dirección no puede superar los 150 caracteres.',
            'address.regex' => 'La dirección contiene caracteres no válidos o repetición indebida de caracteres.',
            'admin_name.required' => 'El nombre del administrador es obligatorio.',
            'admin_name.min' => 'El nombre del administrador debe tener al menos 2 caracteres.',
            'admin_name.max' => 'El nombre del administrador no puede superar los 80 caracteres.',
            'admin_name.regex' => 'El nombre del administrador solo debe contener letras, espacios, puntos o guiones.',
            'admin_email.required' => 'El correo del administrador es obligatorio.',
            'admin_email.unique' => 'Ese correo ya está registrado en la plataforma.',
            'admin_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $plainPassword = ! empty($validated['admin_password'])
            ? $validated['admin_password']
            : Str::password(16, symbols: true);

        $nightlyReportEnabled = $request->boolean('nightly_report_enabled', true);
        $superadminId = auth()->id();

        [$business, $admin] = DB::transaction(function () use ($validated, $plainPassword, $superadminId, $nightlyReportEnabled) {
            $nowBogota = Carbon::now('America/Bogota');

            $business = new Business();
            $business->name = $validated['name'];
            $business->business_type = $validated['business_type'] ?? 'retail';
            $business->nit = $validated['nit'] ?? null;
            $business->phone = $validated['phone'] ?? null;
            $business->email = $validated['email'] ?? null;
            $business->address = $validated['address'] ?? null;
            $business->status = 'active';
            $business->is_active = true;
            $business->nightly_report_enabled = $nightlyReportEnabled;
            $business->subscription_starts_at = $nowBogota;
            $business->subscription_ends_at = $nowBogota->copy()->addDays(30);
            $business->save();

            $admin = new User();
            $admin->name = $validated['admin_name'];
            $admin->email = strtolower($validated['admin_email']);
            $admin->password = Hash::make($plainPassword);
            $admin->is_superadmin = false;
            $admin->must_change_password = true;
            $admin->save();

            $adminRole = Role::firstOrCreate(
                ['slug' => Role::ROLE_ADMIN],
                [
                    'name' => 'Administrador',
                    'description' => 'Acceso y administración total del emprendimiento',
                ]
            );

            $business->users()->attach($admin->id, [
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);

            Log::info("Negocio #{$business->id} ({$business->name}) y administrador inicial #{$admin->id} creados por el superadministrador #{$superadminId}.");

            return [$business, $admin];
        });

        try {
            Mail::to($admin->email)->send(new WelcomeTenantMail($business, $admin, $plainPassword));
            Log::info("Correo de bienvenida enviado exitosamente al administrador #{$admin->id} ({$admin->email}) del negocio #{$business->id}.");
        } catch (\Throwable $e) {
            Log::error("Error enviando correo de bienvenida al comercio #{$business->id} ({$admin->email}): " . $e->getMessage(), [
                'business_id' => $business->id,
                'email' => $admin->email,
            ]);
        }

        session()->flash('generated_password', $plainPassword);

        return redirect()->route('superadmin.businesses.index')
            ->with('status', "Negocio '{$business->name}' y su administrador creados exitosamente.");
    }

    /**
     * Show the form for editing the business.
     */
    public function edit(Business $business): View
    {
        return view('superadmin.businesses.edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the business information.
     */
    public function update(Request $request, Business $business): RedirectResponse
    {
        if ($request->has('phone') && $request->filled('phone')) {
            $rawPhone = trim((string)$request->input('phone'));
            if (!preg_match('/[a-zA-Z]/', $rawPhone)) {
                $request->merge(['phone' => preg_replace('/[\s\-()]/', '', $rawPhone)]);
            }
        }
        if ($request->has('nit') && trim((string)$request->input('nit')) === '') {
            $request->merge(['nit' => null]);
        }
        if ($request->has('address') && trim((string)$request->input('address')) === '') {
            $request->merge(['address' => null]);
        }
        if ($request->has('email') && trim((string)$request->input('email')) === '') {
            $request->merge(['email' => null]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'business_type' => ['nullable', 'string', 'in:retail,restaurant'],
            'nit' => ['nullable', 'string', 'min:5', 'max:15', 'regex:/^\d{5,15}(-\d)?$/'],
            'phone' => ['nullable', 'string', 'min:7', 'max:15', 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => [
                'nullable',
                'string',
                'min:5',
                'max:150',
                'regex:/^(?!.*(.)\1{5,})[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s#\-\.,\/°]+$/u',
            ],
            'subscription_ends_at' => ['nullable', 'date'],
            'nightly_report_enabled' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre del negocio es obligatorio.',
            'name.min' => 'El nombre del negocio debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre del negocio no puede superar los 100 caracteres.',
            'nit.min' => 'El NIT debe tener al menos 5 dígitos.',
            'nit.max' => 'El NIT no puede superar los 15 caracteres.',
            'nit.regex' => 'El NIT debe contener entre 5 y 15 dígitos numéricos (con guion opcional).',
            'phone.min' => 'El teléfono debe tener al menos 7 dígitos.',
            'phone.max' => 'El teléfono no puede superar los 15 caracteres.',
            'phone.regex' => 'El teléfono debe contener únicamente números (entre 7 y 15 dígitos) y opcionalmente el prefijo +.',
            'address.min' => 'La dirección debe tener al menos 5 caracteres.',
            'address.max' => 'La dirección no puede superar los 150 caracteres.',
            'address.regex' => 'La dirección contiene caracteres no válidos o repetición indebida de caracteres.',
        ]);

        if ($request->has('subscription_ends_at')) {
            $validated['subscription_ends_at'] = ! empty($validated['subscription_ends_at'])
                ? Carbon::parse($validated['subscription_ends_at'], 'America/Bogota')->endOfDay()
                : null;
        } else {
            unset($validated['subscription_ends_at']);
        }

        if ($request->has('nightly_report_enabled')) {
            $validated['nightly_report_enabled'] = $request->boolean('nightly_report_enabled');
        }

        $business->update($validated);

        Log::info("Datos del negocio #{$business->id} ({$business->name}) actualizados por el superadministrador #" . auth()->id() . ".");

        return redirect()->route('superadmin.businesses.index')
            ->with('status', "Negocio '{$business->name}' actualizado correctamente.");
    }

    /**
     * Toggle active/inactive status (suspender / reactivar).
     */
    public function toggleStatus(Business $business): RedirectResponse
    {
        $superadminId = auth()->id();

        if ($business->status === 'active') {
            $business->status = 'inactive';
            $business->is_active = false;
            $business->save();

            Log::info("Negocio #{$business->id} ({$business->name}) suspendido por el superadministrador #{$superadminId}.");

            return back()->with('status', "Negocio '{$business->name}' suspendido exitosamente.");
        }

        $business->status = 'active';
        $business->is_active = true;
        $business->save();

        Log::info("Negocio #{$business->id} ({$business->name}) reactivado por el superadministrador #{$superadminId}.");

        return back()->with('status', "Negocio '{$business->name}' reactivado exitosamente.");
    }

    /**
     * Renew the business monthly subscription by adding 30 calendar days strictly from current cut-off date.
     */
    public function renewSubscription(Business $business): RedirectResponse
    {
        $superadminId = auth()->id();
        $nowBogota = Carbon::now('America/Bogota');

        $currentEnd = $business->subscription_ends_at
            ? $business->subscription_ends_at->copy()->timezone('America/Bogota')
            : null;

        // Se suman 30 días estrictamente a partir de la fecha de corte previa para mantener su ciclo mensual fijo
        if ($currentEnd) {
            $newStartsAt = $currentEnd->copy();
            $newEndsAt = $currentEnd->copy()->addDays(30);
        } else {
            $newStartsAt = $nowBogota;
            $newEndsAt = $nowBogota->copy()->addDays(30);
        }

        $business->subscription_starts_at = $newStartsAt;
        $business->subscription_ends_at = $newEndsAt;
        $business->save();

        Log::info("Suscripción del negocio #{$business->id} ({$business->name}) renovada por 30 días hasta {$newEndsAt->format('Y-m-d H:i:s')} por el superadministrador #{$superadminId}.");

        return back()->with('status', "Suscripción del negocio '{$business->name}' renovada por 30 días (nuevo corte: {$newEndsAt->format('d/m/Y')}).");
    }

    /**
     * Reset the administrator password for a business (generates temporary password).
     */
    public function resetAdminPassword(Business $business): RedirectResponse
    {
        $adminRole = Role::where('slug', Role::ROLE_ADMIN)->first();

        $admin = $business->users()
            ->wherePivot('role_id', $adminRole?->id)
            ->wherePivot('is_active', true)
            ->first()
            ?? $business->users()
                ->wherePivot('role_id', $adminRole?->id)
                ->first();

        if (! $admin) {
            return back()->withErrors(['error' => 'No se encontró un usuario administrador para este negocio.']);
        }

        $tempPassword = Str::password(16, symbols: true);
        $admin->password = Hash::make($tempPassword);
        $admin->must_change_password = true;
        $admin->save();

        session()->flash('temp_password', $tempPassword);

        Log::info("Contraseña temporal restablecida para el usuario administrador #{$admin->id} del negocio #{$business->id} por el superadministrador #" . auth()->id() . ".");

        return back()->with('status', "Contraseña temporal restablecida para el administrador {$admin->name} ({$admin->email}).");
    }

    /**
     * View users associated with a specific business (read-only with activation toggle).
     */
    public function users(Business $business): View
    {
        $users = $business->users()->withPivot('role_id', 'is_active', 'created_at')->get();
        $roles = Role::all()->keyBy('id');

        return view('superadmin.businesses.users', [
            'business' => $business,
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Toggle a user's membership active status in the business.
     */
    public function toggleUserStatus(Business $business, User $user): RedirectResponse
    {
        $membership = $business->users()->where('users.id', $user->id)->firstOrFail();

        $newStatus = ! (bool) $membership->pivot->is_active;

        $business->users()->updateExistingPivot($user->id, [
            'is_active' => $newStatus,
        ]);

        $statusText = $newStatus ? 'activado' : 'desactivado';
        Log::info("Usuario #{$user->id} {$statusText} en el negocio #{$business->id} por el superadministrador #" . auth()->id() . ".");

        return back()->with('status', "Estado del usuario {$user->name} en {$business->name} actualizado a {$statusText}.");
    }

    /**
     * Show form for registering a new collaborator for a business.
     */
    public function createUser(Business $business): View
    {
        return view('superadmin.businesses.users-create', [
            'business' => $business,
        ]);
    }

    /**
     * Store a newly registered collaborator for a business.
     */
    public function storeUser(StoreTenantUserRequest $request, Business $business): RedirectResponse
    {
        $validated = $request->validated();

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : Str::password(16, symbols: true);

        $superadminId = auth()->id();

        $user = DB::transaction(function () use ($validated, $plainPassword, $business, $superadminId) {
            $roleSlug = $validated['role'] === 'admin' ? Role::ROLE_ADMIN : Role::ROLE_EMPLOYEE;
            $roleName = $validated['role'] === 'admin' ? 'Administrador' : 'Empleado';
            $roleDesc = $validated['role'] === 'admin'
                ? 'Acceso y administración total del emprendimiento'
                : 'Colaborador operativo para ventas y atención diaria';

            $role = Role::firstOrCreate(
                ['slug' => $roleSlug],
                ['name' => $roleName, 'description' => $roleDesc]
            );

            $user = new User();
            $user->name = $validated['name'];
            $user->email = strtolower($validated['email']);
            $user->password = Hash::make($plainPassword);
            $user->is_superadmin = false;
            $user->must_change_password = true;
            $user->save();

            $business->users()->attach($user->id, [
                'role_id' => $role->id,
                'is_active' => true,
            ]);

            Log::info("Colaborador #{$user->id} ({$user->email}) creado con rol '{$roleSlug}' en negocio #{$business->id} ({$business->name}) por superadmin #{$superadminId}.");

            return $user;
        });

        try {
            Mail::to($user->email)->send(
                new WelcomeTenantMail($business, $user, $plainPassword, $validated['role'], false)
            );
            Log::info("Correo de bienvenida enviado exitosamente a nuevo colaborador #{$user->id} ({$user->email}) en negocio #{$business->id}.");
        } catch (\Throwable $e) {
            Log::error('Error enviando correo de bienvenida a nuevo colaborador', [
                'business_id' => $business->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $validated['role'],
                'error' => $e->getMessage(),
            ]);
        }

        session()->flash('temp_password', $plainPassword);
        session()->flash('created_user_email', $user->email);
        session()->flash('created_user_name', $user->name);

        return redirect()->route('superadmin.businesses.users', $business)
            ->with('status', "Colaborador '{$user->name}' agregado exitosamente al negocio.");
    }

    /**
     * Update an existing collaborator's details, role, or password.
     */
    public function updateUser(Request $request, Business $business, User $user): RedirectResponse
    {
        $membership = $business->users()->where('users.id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', "regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.'-]+$/u"],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', 'in:admin,employee'],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required' => 'El nombre completo del colaborador es obligatorio.',
            'name.min' => 'El nombre del colaborador debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'name.regex' => 'El nombre del colaborador solo debe contener letras, espacios, puntos o guiones.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico es inválido.',
            'email.unique' => 'Ese correo ya está registrado en la plataforma.',
            'role.required' => 'Debes seleccionar un rol para el colaborador.',
            'role.in' => 'El rol seleccionado no es válido.',
            'password.min' => 'La nueva contraseña temporal debe tener al menos 8 caracteres.',
        ]);

        $adminRole = Role::firstOrCreate(['slug' => Role::ROLE_ADMIN], ['name' => 'Administrador']);
        $isCurrentlyAdmin = (int) $membership->pivot->role_id === (int) $adminRole->id;

        if ($validated['role'] === 'employee' && $isCurrentlyAdmin) {
            $activeAdminsCount = $business->users()
                ->wherePivot('role_id', $adminRole->id)
                ->wherePivot('is_active', true)
                ->count();

            if ($activeAdminsCount <= 1) {
                return back()->withErrors(['role' => 'No es posible cambiar el rol del único administrador activo del negocio. Debes asignar otro administrador primero.']);
            }
        }

        DB::transaction(function () use ($validated, $user, $business) {
            $user->name = $validated['name'];
            $user->email = strtolower($validated['email']);

            $plainPassword = ! empty($validated['password']) ? $validated['password'] : null;
            if ($plainPassword) {
                $user->password = Hash::make($plainPassword);
                $user->must_change_password = true;
            }
            $user->save();

            $targetRoleSlug = $validated['role'] === 'admin' ? Role::ROLE_ADMIN : Role::ROLE_EMPLOYEE;
            $targetRoleName = $validated['role'] === 'admin' ? 'Administrador' : 'Empleado';
            $role = Role::firstOrCreate(['slug' => $targetRoleSlug], ['name' => $targetRoleName]);

            $business->users()->updateExistingPivot($user->id, [
                'role_id' => $role->id,
            ]);

            if ($plainPassword) {
                try {
                    Mail::to($user->email)->send(
                        new WelcomeTenantMail($business, $user, $plainPassword, $validated['role'], false)
                    );
                } catch (\Throwable $e) {
                    Log::error('Error enviando correo con nueva clave al colaborador', [
                        'business_id' => $business->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                session()->flash('temp_password', $plainPassword);
                session()->flash('created_user_email', $user->email);
            }

            Log::info("Colaborador #{$user->id} ({$user->email}) actualizado en negocio #{$business->id} ({$business->name}) por superadmin #" . auth()->id() . ".");
        });

        return back()->with('status', "Datos del colaborador '{$user->name}' actualizados correctamente.");
    }

    /**
     * Wipe all operational transactions generated specifically by this user in the business.
     */
    public function wipeUser(Business $business, User $user): RedirectResponse
    {
        $business->users()->where('users.id', $user->id)->firstOrFail();

        DB::transaction(function () use ($business, $user) {
            $orderIds = RestaurantOrder::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->pluck('id');
            RestaurantOrderItem::withoutGlobalScopes()->whereIn('order_id', $orderIds)->delete();
            RestaurantOrder::withoutGlobalScopes()->whereIn('id', $orderIds)->delete();

            $saleIds = Sale::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->pluck('id');
            SalePayment::withoutGlobalScopes()->whereIn('sale_id', $saleIds)->delete();
            SaleDetail::withoutGlobalScopes()->whereIn('sale_id', $saleIds)->delete();
            Sale::withoutGlobalScopes()->whereIn('id', $saleIds)->delete();

            InventoryMovement::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->delete();

            Expense::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('created_by', $user->id)
                ->forceDelete();

            Log::warning("SuperAdmin #" . auth()->id() . " vació las operaciones del colaborador #{$user->id} ({$user->name}) en negocio #{$business->id} ({$business->name}).");
        });

        return back()->with('status', "Operaciones del colaborador '{$user->name}' vaciadas exitosamente.");
    }

    /**
     * Remove collaborator from the business, purging user account if it has no other businesses.
     */
    public function destroyUser(Business $business, User $user): RedirectResponse
    {
        $membership = $business->users()->where('users.id', $user->id)->firstOrFail();

        $adminRole = Role::firstOrCreate(['slug' => Role::ROLE_ADMIN], ['name' => 'Administrador']);
        $isAdmin = (int) $membership->pivot->role_id === (int) $adminRole->id;

        if ($isAdmin) {
            $activeAdminsCount = $business->users()
                ->wherePivot('role_id', $adminRole->id)
                ->wherePivot('is_active', true)
                ->count();

            if ($activeAdminsCount <= 1) {
                return back()->withErrors(['error' => 'No es posible eliminar al único administrador activo del negocio. Debes asignar otro administrador primero o eliminar el negocio completo.']);
            }
        }

        $userName = $user->name;
        $superadminId = auth()->id();

        DB::transaction(function () use ($business, $user, $superadminId) {
            $orderIds = RestaurantOrder::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->pluck('id');
            RestaurantOrderItem::withoutGlobalScopes()->whereIn('order_id', $orderIds)->delete();
            RestaurantOrder::withoutGlobalScopes()->whereIn('id', $orderIds)->delete();

            $saleIds = Sale::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->pluck('id');
            SalePayment::withoutGlobalScopes()->whereIn('sale_id', $saleIds)->delete();
            SaleDetail::withoutGlobalScopes()->whereIn('sale_id', $saleIds)->delete();
            Sale::withoutGlobalScopes()->whereIn('id', $saleIds)->delete();

            InventoryMovement::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->delete();

            Expense::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('created_by', $user->id)
                ->forceDelete();

            $business->users()->detach($user->id);

            if (! $user->is_superadmin && $user->businesses()->count() === 0) {
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
                $user->delete();
                Log::warning("SuperAdmin #{$superadminId} eliminó definitivamente al colaborador #{$user->id} ({$user->email}) del sistema.");
            } else {
                Log::warning("SuperAdmin #{$superadminId} desvinculó al colaborador #{$user->id} ({$user->email}) del negocio #{$business->id} ({$business->name}).");
            }
        });

        return back()->with('status', "Colaborador '{$userName}' eliminado exitosamente.");
    }

    /**
     * Reset business operational transactions or full catalog and operational data.
     */
    public function resetData(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'mode' => ['required', 'string', 'in:operational,full_catalog'],
            'confirmation_name' => ['required', 'string'],
            'reset_stock' => ['nullable', 'boolean'],
        ], [
            'mode.required' => 'Debes seleccionar el tipo de vaciado.',
            'mode.in' => 'El modo de vaciado seleccionado no es válido.',
            'confirmation_name.required' => 'Debes ingresar el nombre del negocio para confirmar.',
        ]);

        if (trim((string) $request->input('confirmation_name')) !== $business->name) {
            return back()->withErrors(['confirmation_name' => 'El nombre ingresado no coincide con el nombre del negocio.']);
        }

        $businessId = $business->id;
        $mode = $request->input('mode');
        $resetStock = $request->boolean('reset_stock');
        $superadminId = auth()->id();

        DB::transaction(function () use ($businessId, $mode, $resetStock, $superadminId, $business) {
            // 1. Líneas de comanda y comandas
            RestaurantOrderItem::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            RestaurantOrder::withoutGlobalScopes()->where('business_id', $businessId)->delete();

            // 2. Pagos, detalles y ventas
            SalePayment::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            $saleIds = Sale::withoutGlobalScopes()->where('business_id', $businessId)->pluck('id');
            SaleDetail::withoutGlobalScopes()->whereIn('sale_id', $saleIds)->delete();
            Sale::withoutGlobalScopes()->where('business_id', $businessId)->delete();

            // 3. Movimientos de inventario y gastos
            InventoryMovement::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            Expense::withoutGlobalScopes()->where('business_id', $businessId)->forceDelete();

            if ($mode === 'full_catalog') {
                RestaurantTable::withoutGlobalScopes()->where('business_id', $businessId)->delete();
                Product::withoutGlobalScopes()->where('business_id', $businessId)->delete();
                Category::withoutGlobalScopes()->where('business_id', $businessId)->delete();
                Customer::withoutGlobalScopes()->where('business_id', $businessId)->delete();
                Supplier::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            } else {
                RestaurantTable::withoutGlobalScopes()->where('business_id', $businessId)->update(['status' => 'available']);
                if ($resetStock) {
                    Product::withoutGlobalScopes()->where('business_id', $businessId)->update(['stock' => 0]);
                }
            }

            Log::warning("SuperAdmin #{$superadminId} ejecutó vaciado ({$mode}) en el negocio #{$businessId} ({$business->name}).");
        });

        $msg = $mode === 'full_catalog'
            ? "Vaciado total completado para '{$business->name}'. Catálogo y operaciones reseteados."
            : "Reset operativo completado para '{$business->name}'. Ventas y comanda puestas a cero.";

        return redirect()->route('superadmin.businesses.index')->with('status', $msg);
    }

    /**
     * Permanently delete a business and purge all its associated records and orphan users.
     */
    public function destroy(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'confirmation_name' => ['required', 'string'],
        ], [
            'confirmation_name.required' => 'Debes escribir el nombre exacto del negocio para confirmar la eliminación.',
        ]);

        if (trim((string) $request->input('confirmation_name')) !== $business->name) {
            return back()->withErrors(['confirmation_name' => 'El nombre ingresado no coincide con el nombre del negocio.']);
        }

        $businessId = $business->id;
        $businessName = $business->name;
        $superadminId = auth()->id();

        DB::transaction(function () use ($business, $businessId, $businessName, $superadminId, $request) {
            $userIds = $business->users()->pluck('users.id')->toArray();

            RestaurantOrderItem::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            RestaurantOrder::withoutGlobalScopes()->where('business_id', $businessId)->delete();

            SalePayment::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            $saleIds = Sale::withoutGlobalScopes()->where('business_id', $businessId)->pluck('id');
            SaleDetail::withoutGlobalScopes()->whereIn('sale_id', $saleIds)->delete();
            Sale::withoutGlobalScopes()->where('business_id', $businessId)->delete();

            InventoryMovement::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            Expense::withoutGlobalScopes()->where('business_id', $businessId)->forceDelete();

            RestaurantTable::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            Product::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            Category::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            Customer::withoutGlobalScopes()->where('business_id', $businessId)->delete();
            Supplier::withoutGlobalScopes()->where('business_id', $businessId)->delete();

            $business->users()->detach();
            $business->delete();

            $purgedUsersCount = 0;
            foreach ($userIds as $uid) {
                $u = User::find($uid);
                if ($u && ! $u->is_superadmin) {
                    if ($u->businesses()->count() === 0) {
                        if (Schema::hasTable('sessions')) {
                            DB::table('sessions')->where('user_id', $u->id)->delete();
                        }
                        $u->delete();
                        $purgedUsersCount++;
                    }
                }
            }

            Log::warning("SuperAdmin #{$superadminId} eliminó definitivamente el negocio #{$businessId} ({$businessName}) y purgó {$purgedUsersCount} usuario(s) huérfano(s).", [
                'business_id' => $businessId,
                'business_name' => $businessName,
                'purged_users' => $purgedUsersCount,
                'superadmin_id' => $superadminId,
                'ip' => $request->ip(),
            ]);
        });

        return redirect()->route('superadmin.businesses.index')
            ->with('status', "Negocio '{$businessName}' y sus dependencias eliminados definitivamente.");
    }
}
