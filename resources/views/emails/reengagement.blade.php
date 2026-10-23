{{--
    Lembrete com presente (App\Mail\ReengagementMail).

    E-mail não é navegador: tabela para o layout, estilo inline, cor chapada
    (nada de degradê) e imagem em PNG, porque o Gmail não mostra SVG. O visual
    imita o jogo: cartão com borda grossa e a "sombra" sólida embaixo, os
    chips do HUD (XP roxo, vida vermelha, ofensiva laranja) e o botão verde.
--}}
@php
    $boost = $gift === \App\Models\ReengagementEmail::GIFT_XP_BOOST;
    $font = "'Poppins','Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $giftColors = $boost
        ? ['bg' => '#faf5ff', 'border' => '#e9d5ff', 'shadow' => '#d8b4fe', 'label' => '#7e22ce', 'title' => '#9333ea']
        : ['bg' => '#fef2f2', 'border' => '#fecaca', 'shadow' => '#fca5a5', 'label' => '#b91c1c', 'title' => '#dc2626'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $copy['subject'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; width: 100% !important; background: #f3f4f6; }
        a { color: #2563eb; }
        @media (max-width: 600px) {
            .container { width: 100% !important; }
            .card { padding: 28px 20px !important; border-radius: 20px !important; }
            .title { font-size: 24px !important; }
            .cta a { display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f3f4f6;">
    {{-- Texto que aparece ao lado do assunto na caixa de entrada --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        {{ $copy['preheader'] }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;">
        <tr>
            <td align="center" style="padding:28px 12px 40px;">
                <table role="presentation" class="container" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px; max-width:560px;">

                    {{-- Logo (PNG do public/img/logomemorizeblack.svg, com o fundo cinza do e-mail) --}}
                    <tr>
                        <td align="center" style="padding:0 0 16px;">
                            <img src="{{ $logoImage }}" width="150" height="91" alt="Memorize Direito" style="display:block; width:150px; height:91px; border:0; outline:none;">
                        </td>
                    </tr>

                    {{-- Cartão --}}
                    <tr>
                        <td class="card" style="background:#ffffff; border:2px solid #e5e7eb; border-bottom:6px solid #e5e7eb; border-radius:28px; padding:36px 36px 32px; font-family:{!! $font !!};">

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:0 0 8px;">
                                        <img src="{{ $giftImage }}" width="200" height="200" alt="Uma caixa de presente" style="display:block; width:200px; height:200px; border:0; outline:none;">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" align="center" style="font-family:{!! $font !!}; font-size:28px; line-height:1.25; font-weight:900; color:#111827; padding:0 0 14px;">
                                        {{ $copy['title'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-family:{!! $font !!}; font-size:16px; line-height:1.65; color:#4b5563; padding:0 0 24px;">
                                        {{ $copy['intro'] }}
                                    </td>
                                </tr>

                                {{-- O presente --}}
                                <tr>
                                    <td style="padding:0 0 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $giftColors['bg'] }}; border:2px solid {{ $giftColors['border'] }}; border-bottom:5px solid {{ $giftColors['shadow'] }}; border-radius:20px;">
                                            <tr>
                                                <td style="padding:20px 22px; font-family:{!! $font !!};">
                                                    <div style="font-size:12px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; color:{{ $giftColors['label'] }};">Seu presente</div>
                                                    <div style="font-size:26px; line-height:1.2; font-weight:900; color:{{ $giftColors['title'] }}; padding:6px 0 8px;">
                                                        {{ $copy['gift_title'] }}
                                                    </div>
                                                    <div style="font-size:15px; line-height:1.6; color:#374151;">{{ $copy['gift_text'] }}</div>
                                                    @if ($copy['gift_note'])
                                                        <div style="font-size:13px; line-height:1.5; color:#6b7280; padding-top:8px;">{{ $copy['gift_note'] }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                @if ($copy['goal'])
                                    <tr>
                                        <td style="padding:0 0 24px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eff6ff; border:2px solid #bfdbfe; border-radius:16px;">
                                                <tr>
                                                    <td style="padding:14px 18px; font-family:{!! $font !!}; font-size:15px; line-height:1.55; color:#1e40af; font-weight:600;">
                                                        {{ $copy['goal'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                {{-- O botão verde do jogo: a borda de baixo mais grossa faz o "3D" --}}
                                <tr>
                                    <td class="cta" align="center" style="padding:4px 0 12px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:360px;">
                                            <tr>
                                                <td align="center" bgcolor="#22c55e" style="background:#22c55e; border:4px solid #15803d; border-bottom-width:8px; border-radius:12px;">
                                                    <a href="{{ $giftUrl }}" target="_blank" style="display:block; padding:13px 24px; font-family:{!! $font !!}; font-size:18px; font-weight:800; color:#ffffff; text-decoration:none;">
                                                        {{ $copy['cta'] }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-family:{!! $font !!}; font-size:13px; line-height:1.5; color:#6b7280; padding:0 0 26px;">
                                        {{ $copy['cta_note'] }}
                                    </td>
                                </tr>

                                @if ($stats)
                                    {{-- Os chips do HUD, do jeito que a pessoa deixou --}}
                                    <tr>
                                        <td style="border-top:2px solid #f3f4f6; padding:22px 0 0;">
                                            <div style="font-family:{!! $font !!}; font-size:12px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; color:#9ca3af; text-align:center; padding-bottom:12px;">Do jeito que você deixou</div>
                                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding:0 4px 8px;">
                                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                                                            <td style="background:#f3e8ff; border-radius:999px; padding:7px 14px; font-family:{!! $font !!}; font-size:14px; font-weight:800; color:#7e22ce; white-space:nowrap;">
                                                                XP {{ number_format($stats['xp'], 0, ',', '.') }}
                                                            </td>
                                                        </tr></table>
                                                    </td>
                                                    @if ($stats['lives'] !== null)
                                                        <td style="padding:0 4px 8px;">
                                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                                                                <td style="background:#fee2e2; border-radius:999px; padding:7px 14px; font-family:{!! $font !!}; font-size:14px; font-weight:800; color:#b91c1c; white-space:nowrap;">
                                                                    {{ $stats['lives'] }} {{ $stats['lives'] === 1 ? 'vida' : 'vidas' }}
                                                                </td>
                                                            </tr></table>
                                                        </td>
                                                    @endif
                                                    @if ($stats['record'] > 0)
                                                        <td style="padding:0 4px 8px;">
                                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                                                                <td style="background:#ffedd5; border-radius:999px; padding:7px 14px; font-family:{!! $font !!}; font-size:14px; font-weight:800; color:#c2410c; white-space:nowrap;">
                                                                    Recorde de ofensiva: {{ $stats['record'] }} {{ $stats['record'] === 1 ? 'dia' : 'dias' }}
                                                                </td>
                                                            </tr></table>
                                                        </td>
                                                    @endif
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td style="font-family:{!! $font !!}; font-size:14px; line-height:1.6; color:#6b7280; padding:{{ $stats ? '14px' : '0' }} 0 0;">
                                        {{ $copy['ps'] }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Rodapé --}}
                    <tr>
                        <td align="center" style="padding:22px 24px 0; font-family:{!! $font !!}; font-size:12px; line-height:1.6; color:#9ca3af;">
                            Você recebeu este e-mail porque tem uma conta no Memorize Direito com {{ $userEmail }}.<br>
                            Não quer mais lembretes? <a href="{{ $unsubscribeUrl }}" target="_blank" style="color:#6b7280; text-decoration:underline;">Parar de receber</a>
                            <br><br>
                            <strong style="color:#6b7280;">Memorize Direito</strong> · Lei seca virou jogo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
