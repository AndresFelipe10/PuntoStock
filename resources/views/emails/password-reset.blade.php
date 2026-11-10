<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de Contraseña — PuntoStock POS</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .content {
            padding: 32px 28px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
            margin: 0 0 12px 0;
            letter-spacing: -0.3px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin: 0 0 24px 0;
        }
        .cta-container {
            text-align: center;
            margin: 28px 0;
        }
        .cta-button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            text-align: center;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.02em;
        }
        .alert-box {
            background-color: #f8fafc;
            border-left: 3px solid #2563eb;
            padding: 14px 18px;
            border-radius: 0 8px 8px 0;
            margin: 24px 0;
            border-top: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }
        .alert-text {
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
            margin: 0;
        }
        .fallback-box {
            margin-top: 24px;
            padding: 16px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .fallback-intro {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }
        .fallback-url {
            margin: 0;
            word-break: break-all;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            color: #2563eb;
            line-height: 1.5;
        }
    </style>
</head>
<body style="padding: 24px 12px; background-color: #f1f5f9; margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

<div class="container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    {{-- Header Oficial con Logo --}}
    @include('emails.partials.header-logo', [
        'subtitle' => 'Recuperación de Acceso y Seguridad de Cuenta'
    ])

    {{-- Contenido Principal --}}
    <div class="content" style="padding: 32px 28px;">
        <h2 class="greeting" style="font-size: 20px; font-weight: 600; color: #0f172a; margin: 0 0 12px 0; letter-spacing: -0.3px;">
            ¡Hola, {{ $user->name ?? 'Usuario' }}!
        </h2>

        <p class="intro-text" style="font-size: 14px; line-height: 1.6; color: #334155; margin: 0 0 24px 0;">
            Hemos recibido una solicitud para restablecer la contraseña de acceso asociada a tu cuenta (<strong>{{ $email }}</strong>) en la plataforma <strong>PuntoStock POS</strong>.
        </p>

        {{-- Botón Principal de Acción (CTA Corporativo) --}}
        <div class="cta-container" style="text-align: center; margin: 28px 0;">
            <a href="{{ $resetUrl }}" class="cta-button" target="_blank" style="display: inline-block; background-color: #2563eb; color: #ffffff !important; text-decoration: none; text-align: center; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; letter-spacing: 0.02em;">
                Restablecer mi Contraseña &rarr;
            </a>
        </div>

        {{-- Tarjeta de Aviso de Seguridad --}}
        <div class="alert-box" style="background-color: #f8fafc; border-left: 3px solid #2563eb; padding: 14px 18px; border-radius: 0 8px 8px 0; margin: 24px 0; border-top: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
            <p class="alert-text" style="font-size: 13px; line-height: 1.6; color: #334155; margin: 0;">
                <strong style="font-weight: 600; color: #0f172a;">Aviso de Seguridad:</strong> Por tu protección, este enlace de recuperación es de un solo uso y caducará en <strong>{{ $expireMinutes }} minutos</strong>. Al confirmar tu nueva clave, cualquier sesión abierta en otros dispositivos será cerrada automáticamente. Si no solicitaste este cambio, puedes ignorar este mensaje con total tranquilidad; tu contraseña actual seguirá siendo válida.
            </p>
        </div>

        {{-- Bloque de Enlace Alternativo (Fallback URL) --}}
        <div class="fallback-box" style="margin-top: 24px; padding: 16px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            <p class="fallback-intro" style="margin: 0 0 8px 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                Si presentas inconvenientes con el botón anterior, copia y pega la siguiente URL en tu navegador web:
            </p>
            <p class="fallback-url" style="margin: 0; word-break: break-all; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; color: #2563eb; line-height: 1.5;">
                <a href="{{ $resetUrl }}" target="_blank" style="color: #2563eb; text-decoration: none;">
                    {{ $resetUrl }}
                </a>
            </p>
        </div>
    </div>

    {{-- Footer Legal Estandarizado --}}
    @include('emails.partials.footer')
</div>

</body>
</html>

