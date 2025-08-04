<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Cookie, Settings, BarChart3, Shield, Globe } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
</script>

<style>
.heading-font {
    font-family: 'Poppins', sans-serif;
}

.legal-content {
    line-height: 1.8;
}

.legal-content h2 {
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.legal-content h3 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.legal-content p {
    margin-bottom: 1rem;
}

.legal-content ul {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.legal-content li {
    margin-bottom: 0.5rem;
}

.cookie-table {
    overflow-x: auto;
}

.cookie-table table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.cookie-table th,
.cookie-table td {
    border: 1px solid #e5e7eb;
    padding: 0.75rem;
    text-align: left;
}

.cookie-table th {
    background-color: #f9fafb;
    font-weight: 600;
}
</style>

<template>
    <Head title="Política de Cookies - Memorize Direito">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="true">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </Head>
    
    <div class="flex min-h-screen flex-col bg-white text-gray-800">
        <!-- Header -->
        <header class="w-full bg-white border-b border-gray-200 sticky top-0 z-50">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <Link :href="route('home')" class="flex items-center gap-x-2">
                        <AppLogo />
                    </Link>
                </div>
                
                <Link 
                    :href="route('home')" 
                    class="flex items-center gap-2 text-gray-600 hover:text-green-600 font-medium transition-colors"
                >
                    <ArrowLeft class="h-5 w-5" />
                    <span>Voltar ao início</span>
                </Link>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="w-full py-16 bg-gradient-to-br from-slate-50 to-gray-100">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                <div class="flex justify-center mb-6">
                    <div class="p-4 bg-orange-100 rounded-full">
                        <Cookie class="h-12 w-12 text-orange-600" />
                    </div>
                </div>
                <h1 class="text-4xl sm:text-5xl font-bold heading-font text-gray-900 mb-6">
                    Política de <span class="text-orange-600">Cookies</span>
                </h1>
                <p class="text-xl text-gray-600 leading-relaxed">
                    Entenda como utilizamos cookies para melhorar sua experiência na plataforma
                </p>
                <div class="mt-8 text-sm text-gray-500">
                    <p>Última atualização: 3 de agosto de 2025</p>
                </div>
            </div>
        </section>

        <!-- Content Section -->
        <section class="w-full py-16 bg-white">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div class="legal-content text-gray-700">
                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Cookie class="h-6 w-6 text-orange-600" />
                        1. O que são Cookies?
                    </h2>
                    <p>
                        Cookies são pequenos arquivos de texto armazenados em seu dispositivo quando você visita nossa plataforma. Eles nos ajudam a:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Lembrar suas preferências e configurações</li>
                        <li>Manter você conectado durante sua sessão</li>
                        <li>Entender como você usa nossa plataforma</li>
                        <li>Melhorar sua experiência de aprendizado</li>
                        <li>Fornecer conteúdo personalizado</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Settings class="h-6 w-6 text-orange-600" />
                        2. Tipos de Cookies que Utilizamos
                    </h2>

                    <h3 class="text-xl font-semibold text-gray-800">Cookies Essenciais</h3>
                    <p>
                        Estes cookies são necessários para o funcionamento básico da plataforma e não podem ser desabilitados:
                    </p>
                    <div class="cookie-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Finalidade</th>
                                    <th>Duração</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>memorize_session</td>
                                    <td>Manter sua sessão ativa</td>
                                    <td>Sessão</td>
                                </tr>
                                <tr>
                                    <td>csrf_token</td>
                                    <td>Proteção contra ataques CSRF</td>
                                    <td>Sessão</td>
                                </tr>
                                <tr>
                                    <td>user_auth</td>
                                    <td>Autenticação do usuário</td>
                                    <td>30 dias</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-800">Cookies de Funcionalidade</h3>
                    <p>
                        Estes cookies permitem que a plataforma lembre suas escolhas e forneça recursos aprimorados:
                    </p>
                    <div class="cookie-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Finalidade</th>
                                    <th>Duração</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>user_preferences</td>
                                    <td>Salvar preferências de estudo</td>
                                    <td>1 ano</td>
                                </tr>
                                <tr>
                                    <td>theme_mode</td>
                                    <td>Lembrar modo escuro/claro</td>
                                    <td>6 meses</td>
                                </tr>
                                <tr>
                                    <td>language_pref</td>
                                    <td>Idioma preferido</td>
                                    <td>1 ano</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-800">Cookies de Performance</h3>
                    <p>
                        Estes cookies nos ajudam a entender como você interage com a plataforma:
                    </p>
                    <div class="cookie-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Finalidade</th>
                                    <th>Duração</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>_ga</td>
                                    <td>Google Analytics - análise de uso</td>
                                    <td>2 anos</td>
                                </tr>
                                <tr>
                                    <td>_gid</td>
                                    <td>Google Analytics - sessões</td>
                                    <td>24 horas</td>
                                </tr>
                                <tr>
                                    <td>study_analytics</td>
                                    <td>Análise de progresso de estudos</td>
                                    <td>1 ano</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <BarChart3 class="h-6 w-6 text-orange-600" />
                        3. Cookies de Terceiros
                    </h2>
                    <p>
                        Utilizamos alguns serviços de terceiros que podem definir seus próprios cookies:
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800">Google Analytics</h3>
                    <p>
                        Utilizamos o Google Analytics para entender como os usuários interagem com nossa plataforma. Este serviço coleta informações como:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Páginas visitadas e tempo gasto</li>
                        <li>Dispositivo e navegador usado</li>
                        <li>Localização geográfica aproximada</li>
                        <li>Como você chegou à nossa plataforma</li>
                    </ul>
                    <p>
                        Você pode optar por não participar do Google Analytics visitando: 
                        <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" class="text-green-600 hover:text-green-700 underline">
                            https://tools.google.com/dlpage/gaoptout
                        </a>
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800">Stripe (Pagamentos)</h3>
                    <p>
                        Para processar pagamentos de forma segura, utilizamos o Stripe, que pode definir cookies para:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Detectar fraudes</li>
                        <li>Processar pagamentos de forma segura</li>
                        <li>Lembrar informações de pagamento (se autorizado)</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Globe class="h-6 w-6 text-orange-600" />
                        4. Gerenciamento de Cookies
                    </h2>
                    <p>
                        Você tem controle total sobre os cookies em seu dispositivo:
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800">No Navegador</h3>
                    <p>
                        Você pode gerenciar cookies através das configurações do seu navegador:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Chrome:</strong> Configurações > Privacidade e segurança > Cookies</li>
                        <li><strong>Firefox:</strong> Configurações > Privacidade e Segurança > Cookies</li>
                        <li><strong>Safari:</strong> Preferências > Privacidade > Cookies</li>
                        <li><strong>Edge:</strong> Configurações > Permissões de site > Cookies</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Em Nossa Plataforma</h3>
                    <p>
                        Oferecemos um centro de preferências de cookies onde você pode:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Aceitar ou rejeitar categorias específicas de cookies</li>
                        <li>Visualizar informações detalhadas sobre cada cookie</li>
                        <li>Alterar suas preferências a qualquer momento</li>
                        <li>Acessar através de: Configurações da Conta > Privacidade > Cookies</li>
                    </ul>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
                        <h4 class="font-semibold text-amber-800 mb-2">⚠️ Importante:</h4>
                        <p class="text-amber-700">
                            Desabilitar cookies essenciais pode afetar o funcionamento da plataforma. 
                            Alguns recursos podem não funcionar corretamente sem estes cookies.
                        </p>
                    </div>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Shield class="h-6 w-6 text-orange-600" />
                        5. Segurança dos Cookies
                    </h2>
                    <p>
                        Implementamos medidas de segurança para proteger os cookies:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li><strong>HTTPS:</strong> Todos os cookies são transmitidos de forma criptografada</li>
                        <li><strong>Secure Flag:</strong> Cookies só são enviados através de conexões seguras</li>
                        <li><strong>HttpOnly:</strong> Cookies importantes não são acessíveis via JavaScript</li>
                        <li><strong>SameSite:</strong> Proteção contra ataques de falsificação de solicitação</li>
                        <li><strong>Expiração:</strong> Cookies têm prazo de validade definido</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">6. Consentimento e Base Legal</h2>
                    <p>
                        Nossa base legal para o uso de cookies inclui:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Consentimento:</strong> Para cookies de analytics e marketing</li>
                        <li><strong>Interesse Legítimo:</strong> Para cookies de funcionalidade</li>
                        <li><strong>Necessidade Contratual:</strong> Para cookies essenciais ao serviço</li>
                    </ul>
                    <p>
                        Você pode retirar seu consentimento a qualquer momento através das configurações de cookies.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">7. Alterações nesta Política</h2>
                    <p>
                        Podemos atualizar esta Política de Cookies ocasionalmente. Quando fizermos alterações significativas:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Atualizaremos a data de "última atualização"</li>
                        <li>Notificaremos você através de banner na plataforma</li>
                        <li>Solicitaremos novo consentimento quando necessário</li>
                        <li>Enviaremos notificação por e-mail para alterações importantes</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">8. Contato</h2>
                    <p>
                        Se você tiver dúvidas sobre nossa utilização de cookies:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mt-4">
                        <p><strong>E-mail:</strong> cookies@memorizeDireito.com.br</p>
                        <p><strong>Endereço:</strong> Rua Clóvis Beviláqua, n.º 687, bairro Lagoa Seca, Natal/RN</p>
                        <p><strong>CNPJ:</strong> 51.337.580/0001-48</p>
                        <p><strong>Nome Fantasia:</strong> Advable LTDA - Soluções jurídicas</p>
                        <p><strong>Suporte Online:</strong> chat disponível 24/7 na plataforma</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cookie Preferences CTA -->
        <section class="w-full py-16 bg-orange-50">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                <h2 class="text-3xl font-bold heading-font text-gray-800 mb-4">
                    Gerencie suas Preferências de Cookies
                </h2>
                <p class="text-xl text-gray-600 mb-8">
                    Você tem controle total sobre quais cookies aceitar em nossa plataforma.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        :href="route('play.map')"
                        class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-orange-500 text-white font-bold text-xl rounded-lg border-4 border-orange-700 transition-all hover:transform hover:translate-y-1"
                        style="box-shadow: 0 6px 0 rgb(194, 65, 12);"
                        onmouseover="this.style.boxShadow = '0 4px 0 rgb(194, 65, 12)'"
                        onmouseout="this.style.boxShadow = '0 6px 0 rgb(194, 65, 12)'"
                    >
                        <Settings class="h-6 w-6" />
                        Aceitar e Continuar
                    </Link>
                    <Link
                        :href="route('home')"
                        class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-gray-900 font-bold text-xl rounded-lg border-4 border-gray-300 transition-all hover:transform hover:translate-y-1"
                        style="box-shadow: 0 6px 0 rgb(209, 213, 219);"
                        onmouseover="this.style.boxShadow = '0 4px 0 rgb(209, 213, 219)'"
                        onmouseout="this.style.boxShadow = '0 6px 0 rgb(209, 213, 219)'"
                    >
                        Voltar ao Início
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full py-12 bg-gray-900 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                    <div class="text-center md:text-left">
                        <div class="flex items-center gap-2 mb-4 justify-center md:justify-start">
                            <img 
                                src="/img/logomemorize.svg"
                                style="height: 80px;"
                                alt="Logo" 
                            />
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-4 text-sm text-gray-400">
                        <Link :href="route('privacy-policy')" class="hover:text-white transition-colors">Políticas de Privacidade</Link>
                        <Link :href="route('cookies')" class="hover:text-white transition-colors">Cookies</Link>
                        <Link :href="route('terms')" class="hover:text-white transition-colors">Termos de Uso</Link>
                    </div>
                </div>
                
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p class="text-gray-400">© 2025 Memorize Direito. Todos os direitos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
