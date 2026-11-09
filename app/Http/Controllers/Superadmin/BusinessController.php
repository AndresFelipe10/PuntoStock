<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\StoreTenantUserRequest;
use App\Mail\WelcomeTenantMail;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
}
