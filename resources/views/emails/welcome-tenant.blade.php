<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a PuntoStock POS - {{ $business->name }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            -webkit-text-size-adjust: 100%;
        }
        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .content {
            padding: 32px 28px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 12px 0;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin: 0 0 24px 0;
        }
        .credentials-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 24px;
        }
        .card-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: #2563eb;
            letter-spacing: 0.5px;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
        }
        .cred-row {
            margin-bottom: 12px;
        }
        .cred-row:last-child {
            margin-bottom: 0;
        }
        .cred-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .cred-value {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            word-break: break-all;
        }
        .password-box {
            display: inline-block;
            background-color: #0f172a;
            color: #38bdf8;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 15px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 6px;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .alert-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 14px 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 24px;
        }
        .alert-text {
            font-size: 13px;
            line-height: 1.5;
            color: #1e40af;
            margin: 0;
        }
        .steps-container {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .steps-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 10px 0;
        }
        .step-item {
            font-size: 13px;
            color: #334155;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .step-item:last-child {
            margin-bottom: 0;
        }
        .cta-button {
            display: block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            text-align: center;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            margin: 0 0 24px 0;
        }
    </style>
</head>
<body style="padding: 24px 12px; background-color: #f1f5f9;">

<div class="container">
    {{-- Header Oficial con Logo --}}
    @include('emails.partials.header-logo', [
        'subtitle' => 'Credenciales de Acceso y Alta de Comercio'
    ])

    {{-- Content --}}
    <div class="content">
        <h2 class="greeting">¡Hola, {{ $adminUser->name }}!</h2>
        <p class="intro-text">
            Te damos una cordial bienvenida a <strong>PuntoStock POS</strong>. Tu comercio <strong>{{ $business->name }}</strong> ha sido registrado exitosamente en nuestra plataforma de gestión de ventas e inventario en tiempo real.
        </p>

        {{-- Tarjeta de Credenciales --}}
        <div class="credentials-card">
            <div class="card-title">🔐 Tus Credenciales de Administrador</div>
            
            <div class="cred-row">
                <div class="cred-label">Enlace de Acceso al Sistema</div>
                <div class="cred-value">
                    <a href="{{ config('app.url') }}/login" style="color: #2563eb; text-decoration: none;">
                        {{ config('app.url') }}/login
                    </a>
                </div>
            </div>

            <div class="cred-row" style="margin-top: 12px;">
                <div class="cred-label">Correo Electrónico de Usuario</div>
                <div class="cred-value">{{ $adminUser->email }}</div>
            </div>

            <div class="cred-row" style="margin-top: 12px;">
                <div class="cred-label">Contraseña Temporal</div>
                <div class="password-box">{{ $plainPassword }}</div>
            </div>
        </div>

        {{-- Alerta de Seguridad: Cambio de Clave Obligatorio --}}
        <div class="alert-box">
            <p class="alert-text">
                <strong>🔒 Política de Seguridad Obligatoria:</strong> Al iniciar sesión por primera vez con estas credenciales, el sistema te solicitará de inmediato cambiar esta contraseña temporal por tu clave personal definitiva.
            </p>
        </div>

        {{-- Pasos para Iniciar --}}
        <div class="steps-container">
            <div class="steps-title">¿Cómo ingresar a tu panel?</div>
            <div class="step-item">1. Haz clic en el botón azul de abajo o ingresa a <strong>{{ config('app.url') }}/login</strong>.</div>
            <div class="step-item">2. Digita tu correo <code>{{ $adminUser->email }}</code> y la contraseña temporal proporcionada arriba.</div>
            <div class="step-item">3. Establece tu nueva contraseña segura y accede al panel de control de <strong>{{ $business->name }}</strong>.</div>
        </div>

        {{-- Botón CTA --}}
        <a href="{{ config('app.url') }}/login" class="cta-button" target="_blank">
            Acceder a Mi Cuenta en PuntoStock POS &rarr;
        </a>

        <p style="font-size: 12px; color: #64748b; margin: 0; text-align: center; line-height: 1.5;">
            Si tienes dudas o necesitas asistencia con la configuración inicial de tu inventario, comunícate con nuestro equipo de soporte técnico.
        </p>
    </div>

    {{-- Footer Legal --}}
    @include('emails.partials.footer', [
        'footerNote' => 'Has recibido este mensaje porque tu comercio ' . $business->name . ' fue dado de alta en la plataforma PuntoStock POS.'
    ])
</div>

</body>
</html>

