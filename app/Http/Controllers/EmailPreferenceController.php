<?php

namespace App\Http\Controllers;

use App\Mail\ReengagementMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Parar ou voltar a receber os lembretes por e-mail.
 *
 * O link do e-mail é assinado (sem login). O POST do descadastro também atende
 * o "cancelar inscrição" do Gmail e do Outlook, que chega sem token CSRF (ver
 * bootstrap/app.php). Quem está logado também muda isso nas configurações.
 */
class EmailPreferenceController extends Controller
{
    public function show(User $user): Response
    {
        return Inertia::render('Emails/Unsubscribe', [
            'name' => ReengagementMail::firstName($user->name),
            'email' => $user->email,
            'optedOut' => ! $user->receivesEmails(),
            'unsubscribeUrl' => URL::signedRoute('emails.unsubscribe', ['user' => $user->id], absolute: false),
            'resubscribeUrl' => URL::signedRoute('emails.resubscribe', ['user' => $user->id], absolute: false),
        ]);
    }

    public function unsubscribe(User $user): RedirectResponse
    {
        $user->email_opt_out_at ??= now();
        $user->save();

        return redirect(ReengagementMail::unsubscribeUrl($user));
    }

    public function resubscribe(User $user): RedirectResponse
    {
        $user->email_opt_out_at = null;
        $user->save();

        return redirect(ReengagementMail::unsubscribeUrl($user));
    }

    /** Liga e desliga pelas configurações do perfil. */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['receives_emails' => ['required', 'boolean']]);

        $user = $request->user();
        $user->email_opt_out_at = $validated['receives_emails'] ? null : ($user->email_opt_out_at ?? now());
        $user->save();

        return to_route('profile.edit');
    }
}
