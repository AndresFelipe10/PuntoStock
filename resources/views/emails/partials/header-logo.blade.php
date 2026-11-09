@php
    $logoPath = public_path('apple-touch-icon.png');
    $logoSrc = (isset($message) && file_exists($logoPath))
        ? $message->embed($logoPath)
        : url('/apple-touch-icon.png');
@endphp
<div style="background-color: #0f172a; padding: 28px 24px 24px 24px; text-align: center; border-bottom: 1px solid #1e293b;">
    <a href="{{ config('app.url') }}" target="_blank" style="text-decoration: none; display: inline-block;">
        <img src="{{ $logoSrc }}" 
             alt="PuntoStock POS" 
             width="56" 
             height="56" 
             style="width: 56px; height: 56px; border-radius: 12px; display: block; margin: 0 auto 12px auto; border: 0; outline: none; text-decoration: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);">
    </a>
    <h1 style="margin: 0; font-size: 22px; font-weight: 600; color: #ffffff; letter-spacing: -0.5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
        Punto<span style="color: #2563eb;">Stock</span>
        <span style="display: inline-block; background-color: #1e293b; color: #94a3b8; font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 9999px; margin-left: 6px; letter-spacing: 0.05em; text-transform: uppercase; vertical-align: middle; border: 1px solid #334155; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">SISTEMA POS</span>
    </h1>
    @if(!empty($subtitle))
        <p style="margin: 6px 0 0 0; font-size: 13px; font-weight: 500; color: #94a3b8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
            {{ $subtitle }}
        </p>
    @endif
</div>

