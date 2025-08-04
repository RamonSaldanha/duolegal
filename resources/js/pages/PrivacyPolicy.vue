<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Shield, Eye, Database, UserCheck, Mail } from 'lucide-vue-next';
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
</style>

<template>
    <Head title="Políticas de Privacidade - Memorize Direito">
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
                        <AppLogo theme="light" />
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
                    <div class="p-4 bg-green-100 rounded-full">
                        <Shield class="h-12 w-12 text-green-600" />
                    </div>
                </div>
                <h1 class="text-4xl sm:text-5xl font-bold heading-font text-gray-900 mb-6">
                    Políticas de <span class="text-green-600">Privacidade</span>
                </h1>
                <p class="text-xl text-gray-600 leading-relaxed">
                    Transparência total sobre como coletamos, usamos e protegemos seus dados pessoais
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
                        <Eye class="h-6 w-6 text-green-600" />
                        1. Informações que Coletamos
                    </h2>
                    <p>
                        O Memorize Direito coleta informações necessárias para fornecer nossos serviços educacionais de forma eficiente e personalizada. As informações coletadas incluem:
                    </p>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Informações de Cadastro</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Nome completo</li>
                        <li>Endereço de e-mail</li>
                        <li>Senha (criptografada)</li>
                        <li>Data de nascimento (opcional)</li>
                        <li>Área de interesse jurídico (opcional)</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Dados de Uso da Plataforma</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Progresso nos estudos e estatísticas de aprendizado</li>
                        <li>Tempo gasto na plataforma</li>
                        <li>Artigos estudados e pontuações obtidas</li>
                        <li>Preferências de estudo</li>
                        <li>Histórico de exercícios realizados</li>
                        <li>Tipo de plano utilizado (Gratuito com anúncios ou Premium)</li>
                        <li>Sistema de vidas e seu uso (aplicável ao plano gratuito)</li>
                        <li>Interações com anúncios (aplicável ao plano gratuito)</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Informações Técnicas</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Endereço IP</li>
                        <li>Tipo de navegador e versão</li>
                        <li>Sistema operacional</li>
                        <li>Dados de cookies (conforme nossa Política de Cookies)</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Database class="h-6 w-6 text-green-600" />
                        2. Como Usamos Suas Informações
                    </h2>
                    <p>
                        Utilizamos suas informações pessoais para os seguintes propósitos:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Prestação do Serviço:</strong> Fornecer acesso à plataforma de estudos e acompanhar seu progresso educacional</li>
                        <li><strong>Personalização:</strong> Adaptar o conteúdo e exercícios ao seu nível de conhecimento e objetivos</li>
                        <li><strong>Gestão de Planos:</strong> Administrar seu plano de uso (Gratuito com anúncios ou Premium), incluindo controle de vidas no plano gratuito</li>
                        <li><strong>Publicidade:</strong> Exibir anúncios personalizados para usuários do plano gratuito</li>
                        <li><strong>Comunicação:</strong> Enviar notificações sobre seu progresso, lembretes de estudo e atualizações importantes</li>
                        <li><strong>Melhoria do Serviço:</strong> Analisar dados de uso para aprimorar nossa plataforma e desenvolver novos recursos</li>
                        <li><strong>Segurança:</strong> Detectar e prevenir atividades fraudulentas ou abusivas</li>
                        <li><strong>Suporte:</strong> Responder a suas dúvidas e fornecer assistência técnica</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <UserCheck class="h-6 w-6 text-green-600" />
                        3. Compartilhamento de Informações
                    </h2>
                    <p>
                        O Memorize Direito não vende, aluga ou compartilha suas informações pessoais com terceiros, exceto nas seguintes situações:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Prestadores de Serviço:</strong> Compartilhamos dados com fornecedores que nos ajudam a operar a plataforma (hospedagem, análise de dados, suporte técnico)</li>
                        <li><strong>Parceiros de Publicidade:</strong> Para usuários do plano gratuito, compartilhamos dados limitados com parceiros de anúncios para personalização de publicidade</li>
                        <li><strong>Cumprimento Legal:</strong> Quando exigido por lei, ordem judicial ou autoridades competentes</li>
                        <li><strong>Proteção de Direitos:</strong> Para proteger nossos direitos, propriedade ou segurança, bem como de nossos usuários</li>
                        <li><strong>Dados Agregados:</strong> Podemos compartilhar informações estatísticas agregadas e anonimizadas para pesquisa e desenvolvimento educacional</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">4. Planos de Assinatura e Coleta de Dados</h2>
                    <p>
                        O Memorize Direito oferece dois tipos de planos com diferentes formas de coleta e uso de dados:
                    </p>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Plano Gratuito</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Inclui exibição de anúncios personalizados</li>
                        <li>Sistema de vidas limitadas para controle de uso</li>
                        <li>Coletamos dados de interação com anúncios para personalização</li>
                        <li>Monitoramos o uso de vidas para gerenciar limitações</li>
                        <li>Dados comportamentais podem ser compartilhados com parceiros de publicidade (de forma anonimizada)</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Plano Premium</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Sem anúncios e sem limitação de vidas</li>
                        <li>Não compartilhamos dados com parceiros de publicidade</li>
                        <li>Coleta de dados focada apenas na melhoria da experiência educacional</li>
                        <li>Dados de pagamento processados por parceiros seguros (Stripe)</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">5. Planos e Pagamentos</h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Planos Oferecidos</h3>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Plano Gratuito:</strong> Acesso básico com funcionalidades limitadas</li>
                        <li><strong>Plano Premium:</strong> Acesso completo a todos os recursos e conteúdos</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">6. Proteção de Dados</h2>
                    <p>
                        Implementamos medidas de segurança técnicas e administrativas para proteger suas informações:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Criptografia de dados sensíveis (SSL/TLS)</li>
                        <li>Controle de acesso restrito aos dados pessoais</li>
                        <li>Monitoramento contínuo de segurança</li>
                        <li>Backup regular dos dados</li>
                        <li>Treinamento da equipe em práticas de segurança</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">7. Seus Direitos</h2>
                    <p>
                        De acordo com a Lei Geral de Proteção de Dados (LGPD), você tem os seguintes direitos:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Acesso:</strong> Solicitar informações sobre quais dados pessoais processamos</li>
                        <li><strong>Correção:</strong> Corrigir dados incompletos, inexatos ou desatualizados</li>
                        <li><strong>Exclusão:</strong> Solicitar a exclusão de dados pessoais desnecessários ou tratados em desconformidade</li>
                        <li><strong>Portabilidade:</strong> Solicitar a transferência de dados para outro prestador de serviços</li>
                        <li><strong>Revogação:</strong> Retirar o consentimento a qualquer momento</li>
                        <li><strong>Oposição:</strong> Opor-se ao tratamento de dados em certas circunstâncias</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">8. Retenção de Dados</h2>
                    <p>
                        Mantemos suas informações pessoais apenas pelo tempo necessário para:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Fornecer nossos serviços enquanto sua conta estiver ativa</li>
                        <li>Cumprir obrigações legais e contratuais</li>
                        <li>Resolver disputas e fazer cumprir nossos acordos</li>
                        <li>Após a exclusão da conta, alguns dados podem ser mantidos por até 5 anos para fins legais</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">9. Menores de Idade</h2>
                    <p>
                        Nossa plataforma não é direcionada a menores de 13 anos. Se você tem entre 13 e 18 anos, precisa do consentimento de seus pais ou responsáveis legais para usar nossos serviços. Se descobrirmos que coletamos informações de menores de 13 anos sem consentimento adequado, excluiremos essas informações imediatamente.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">10. Alterações nesta Política</h2>
                    <p>
                        Podemos atualizar esta Política de Privacidade periodicamente. Quando fizermos alterações significativas, notificaremos você através de:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>E-mail para o endereço cadastrado em sua conta</li>
                        <li>Aviso destacado em nossa plataforma</li>
                        <li>Notificação push (se habilitada)</li>
                    </ul>
                    <p>
                        As alterações entram em vigor na data de publicação da versão revisada.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Mail class="h-6 w-6 text-green-600" />
                        11. Contato
                    </h2>
                    <p>
                        Se você tiver dúvidas sobre esta Política de Privacidade ou quiser exercer seus direitos, entre em contato conosco:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mt-4">
                        <p><strong>E-mail:</strong> privacidade@memorizeDireito.com.br</p>
                        <p><strong>Endereço:</strong> Rua Clóvis Beviláqua, n.º 687, bairro Lagoa Seca, Natal/RN</p>
                        <p><strong>CNPJ:</strong> 51.337.580/0001-48</p>
                        <p><strong>Nome Fantasia:</strong> Advable LTDA - Soluções jurídicas</p>
                        <p><strong>Encarregado de Dados (DPO):</strong> dpo@memorizeDireito.com.br</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="w-full py-16 bg-gray-50">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                <h2 class="text-3xl font-bold heading-font text-gray-800 mb-4">
                    Pronto para começar a estudar?
                </h2>
                <p class="text-xl text-gray-600 mb-8">
                    Seus dados estão seguros conosco. Comece sua jornada de aprendizado hoje mesmo.
                </p>
                <Link
                    :href="route('play.map')"
                    class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-green-500 text-white font-bold text-xl rounded-lg border-4 border-green-700 transition-all hover:transform hover:translate-y-1"
                    style="box-shadow: 0 6px 0 rgb(21, 128, 61);"
                    onmouseover="this.style.boxShadow = '0 4px 0 rgb(21, 128, 61)'"
                    onmouseout="this.style.boxShadow = '0 6px 0 rgb(21, 128, 61)'"
                >
                    Começar Gratuitamente
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full py-12 bg-gray-900 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                    <div class="text-center md:text-left">
                        <div class="flex items-center gap-2 mb-4 justify-center md:justify-start">
                            <AppLogo theme="dark" height="80px" />
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
