<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Scale, AlertTriangle, CreditCard, Users } from 'lucide-vue-next';
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
    <Head title="Termos de Uso - Memorize Direito">
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
                    <div class="p-4 bg-blue-100 rounded-full">
                        <FileText class="h-12 w-12 text-blue-600" />
                    </div>
                </div>
                <h1 class="text-4xl sm:text-5xl font-bold heading-font text-gray-900 mb-6">
                    Termos de <span class="text-blue-600">Uso</span>
                </h1>
                <p class="text-xl text-gray-600 leading-relaxed">
                    Condições e diretrizes para uso da plataforma de estudos Memorize Direito
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
                        <FileText class="h-6 w-6 text-blue-600" />
                        1. Aceitação dos Termos
                    </h2>
                    <p>
                        Ao acessar e usar a plataforma Memorize Direito, você concorda em cumprir e estar sujeito aos seguintes termos e condições de uso. Se você não concordar com qualquer parte destes termos, não deverá usar nossos serviços.
                    </p>
                    <p>
                        Estes Termos de Uso constituem um acordo legalmente vinculativo entre você ("Usuário") e a Memorize Direito Ltda. ("Empresa", "nós", "nosso").
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">2. Definições</h2>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Plataforma:</strong> O website, aplicativo móvel e todos os serviços oferecidos pelo Memorize Direito</li>
                        <li><strong>Conteúdo:</strong> Todos os materiais de estudo, exercícios, artigos de lei, vídeos e recursos educacionais</li>
                        <li><strong>Conta:</strong> O perfil pessoal criado pelo usuário para acessar a plataforma</li>
                        <li><strong>Serviços:</strong> Todas as funcionalidades oferecidas pela plataforma, incluindo estudos gamificados, acompanhamento de progresso e recursos premium</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Users class="h-6 w-6 text-blue-600" />
                        3. Cadastro e Conta do Usuário
                    </h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Elegibilidade</h3>
                    <p>
                        Para usar nossos serviços, você deve:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Ter pelo menos 13 anos de idade</li>
                        <li>Se menor de 18 anos, ter consentimento dos pais ou responsáveis</li>
                        <li>Fornecer informações verdadeiras e atualizadas</li>
                        <li>Ter capacidade legal para celebrar contratos</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Responsabilidades da Conta</h3>
                    <p>
                        Você é responsável por:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Manter a confidencialidade de sua senha</li>
                        <li>Todas as atividades realizadas em sua conta</li>
                        <li>Notificar-nos imediatamente sobre uso não autorizado</li>
                        <li>Manter suas informações de contato atualizadas</li>
                        <li>Não compartilhar sua conta com terceiros</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">4. Uso da Plataforma</h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Uso Permitido</h3>
                    <p>
                        Você pode usar nossa plataforma para:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Estudar legislação brasileira através de nossos métodos gamificados</li>
                        <li>Acompanhar seu progresso de aprendizado</li>
                        <li>Acessar materiais de estudo e exercícios</li>
                        <li>Participar de recursos interativos da plataforma</li>
                        <li>Usar ferramentas de análise de desempenho</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Uso Proibido</h3>
                    <p>
                        É expressamente proibido:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Copiar, reproduzir ou distribuir nosso conteúdo sem autorização</li>
                        <li>Usar a plataforma para atividades ilegais ou não autorizadas</li>
                        <li>Tentar hackear, comprometer ou prejudicar a segurança da plataforma</li>
                        <li>Criar múltiplas contas para contornar limitações</li>
                        <li>Usar bots, scrapers ou ferramentas automatizadas</li>
                        <li>Interferir no funcionamento normal da plataforma</li>
                        <li>Assediar, intimidar ou prejudicar outros usuários</li>
                        <li>Publicar conteúdo ofensivo, difamatório ou inadequado</li>
                    </ul>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <Scale class="h-6 w-6 text-blue-600" />
                        5. Propriedade Intelectual
                    </h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Nosso Conteúdo</h3>
                    <p>
                        Todo o conteúdo da plataforma, incluindo mas não limitado a:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Textos, exercícios e metodologia de ensino</li>
                        <li>Design, layout e interface da plataforma</li>
                        <li>Códigos, software e algoritmos</li>
                        <li>Logotipos, marcas e identidade visual</li>
                        <li>Vídeos, áudios e materiais multimídia</li>
                    </ul>
                    <p>
                        São de propriedade exclusiva da Memorize Direito ou de nossos licenciadores, protegidos por leis de direitos autorais e propriedade intelectual.
                    </p>

                    <h3 class="text-xl font-semibold text-gray-800">Seu Conteúdo</h3>
                    <p>
                        Ao enviar qualquer conteúdo para nossa plataforma (como feedback, sugestões ou comentários), você nos concede uma licença mundial, não exclusiva e livre de royalties para usar, modificar e distribuir esse conteúdo.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <CreditCard class="h-6 w-6 text-blue-600" />
                        6. Planos e Pagamentos
                    </h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Planos Oferecidos</h3>
                    <ul class="list-disc text-gray-700">
                        <li><strong>Plano Gratuito:</strong> Acesso básico com funcionalidades limitadas, sistema de vidas e anúncios</li>
                        <li><strong>Plano Premium:</strong> Acesso completo a todos os recursos e conteúdos, sem anúncios e sem limitação de vidas</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Características dos Planos</h3>
                    <p><strong>Plano Gratuito inclui:</strong></p>
                    <ul class="list-disc text-gray-700">
                        <li>Sistema de vidas limitadas para controle de uso</li>
                        <li>Exibição de anúncios durante o uso da plataforma</li>
                        <li>Acesso a conteúdo básico de estudos</li>
                        <li>Funcionalidades limitadas de acompanhamento de progresso</li>
                    </ul>
                    
                    <p><strong>Plano Premium oferece:</strong></p>
                    <ul class="list-disc text-gray-700">
                        <li>Acesso ilimitado sem sistema de vidas</li>
                        <li>Experiência livre de anúncios</li>
                        <li>Acesso completo a todo o conteúdo e recursos</li>
                        <li>Relatórios avançados de progresso e desempenho</li>
                        <li>Suporte prioritário</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Política de Pagamento</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Pagamentos são processados de forma segura através do Stripe</li>
                        <li>Preços podem ser alterados com aviso prévio de 30 dias</li>
                        <li>Assinaturas são renovadas automaticamente</li>
                        <li>Você pode cancelar sua assinatura a qualquer momento</li>
                        <li>Não oferecemos reembolsos parciais por cancelamentos</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Período de Teste</h3>
                    <p>
                        Oferecemos um período de teste gratuito de 7 dias para novos usuários. Durante este período, você terá acesso completo aos recursos premium. Se não cancelar antes do final do período de teste, será cobrada a taxa de assinatura.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">7. Cancelamento e Reembolsos</h2>
                    
                    <h3 class="text-xl font-semibold text-gray-800">Cancelamento pelo Usuário</h3>
                    <ul class="list-disc text-gray-700">
                        <li>Você pode cancelar sua assinatura a qualquer momento através das configurações da conta</li>
                        <li>O cancelamento entrará em vigor no final do período de cobrança atual</li>
                        <li>Você manterá acesso aos recursos premium até o final do período pago</li>
                        <li>Após o cancelamento, sua conta retornará ao plano gratuito</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-gray-800">Política de Reembolso</h3>
                    <p>
                        Oferecemos reembolso total se solicitado dentro de 30 dias da primeira compra. Após este período, não oferecemos reembolsos, mas você pode cancelar para evitar cobranças futuras.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">8. Disponibilidade do Serviço</h2>
                    <p>
                        Embora nos esforcemos para manter a plataforma sempre disponível, não podemos garantir:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Funcionamento ininterrupto da plataforma</li>
                        <li>Ausência de erros ou bugs</li>
                        <li>Compatibilidade com todos os dispositivos</li>
                        <li>Velocidade de carregamento em todas as conexões</li>
                    </ul>
                    <p>
                        Reservamo-nos o direito de realizar manutenções programadas e atualizações que podem afetar temporariamente o acesso à plataforma.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800 flex items-center gap-3">
                        <AlertTriangle class="h-6 w-6 text-blue-600" />
                        9. Limitação de Responsabilidade
                    </h2>
                    <p>
                        O Memorize Direito não será responsável por:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Danos diretos, indiretos, incidentais ou consequenciais</li>
                        <li>Perda de dados, lucros ou oportunidades</li>
                        <li>Interrupções no serviço ou indisponibilidade temporária</li>
                        <li>Resultados de exames ou concursos dos usuários</li>
                        <li>Decisões tomadas com base no conteúdo da plataforma</li>
                    </ul>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
                        <h4 class="font-semibold text-yellow-800 mb-2">⚠️ Importante:</h4>
                        <p class="text-yellow-700">
                            Nossa plataforma é uma ferramenta de apoio aos estudos. O sucesso em exames e concursos depende de múltiplos fatores, incluindo dedicação pessoal, métodos de estudo e preparação adequada.
                        </p>
                    </div>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">10. Suspensão e Encerramento</h2>
                    <p>
                        Podemos suspender ou encerrar sua conta se:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Você violar estes Termos de Uso</li>
                        <li>Suas atividades prejudicarem outros usuários</li>
                        <li>Houver suspeita de atividade fraudulenta</li>
                        <li>Você não pagar taxas devidas</li>
                        <li>A pedido de autoridades legais</li>
                    </ul>
                    <p>
                        Em caso de suspensão permanente, você perderá acesso a todo o conteúdo e progresso da plataforma.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">11. Legislação Aplicável</h2>
                    <p>
                        Estes Termos de Uso são regidos pelas leis brasileiras, especificamente:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018)</li>
                        <li>Marco Civil da Internet (Lei 12.965/2014)</li>
                        <li>Código de Defesa do Consumidor (Lei 8.078/1990)</li>
                        <li>Código Civil Brasileiro</li>
                    </ul>
                    <p>
                        Qualquer disputa será resolvida nos tribunais da comarca de São Paulo, SP.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">12. Alterações nos Termos</h2>
                    <p>
                        Reservamo-nos o direito de modificar estes Termos de Uso a qualquer momento. Quando fizermos alterações:
                    </p>
                    <ul class="list-disc text-gray-700">
                        <li>Publicaremos a versão atualizada em nossa plataforma</li>
                        <li>Notificaremos você por e-mail sobre mudanças significativas</li>
                        <li>Solicitaremos nova aceitação quando necessário</li>
                        <li>As alterações entrarão em vigor 30 dias após a publicação</li>
                    </ul>
                    <p>
                        O uso continuado da plataforma após as alterações constitui aceitação dos novos termos.
                    </p>

                    <h2 class="text-2xl font-bold heading-font text-gray-800">13. Contato e Suporte</h2>
                    <p>
                        Para questões relacionadas a estes Termos de Uso ou suporte geral:
                    </p>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mt-4">
                        <p><strong>E-mail Jurídico:</strong> juridico@memorizeDireito.com.br</p>
                        <p><strong>Suporte Geral:</strong> suporte@memorizeDireito.com.br</p>
                        <p><strong>Endereço:</strong> Rua Clóvis Beviláqua, n.º 687, bairro Lagoa Seca, Natal/RN</p>
                        <p><strong>CNPJ:</strong> 51.337.580/0001-48</p>
                        <p><strong>Nome Fantasia:</strong> Advable LTDA - Soluções jurídicas</p>
                        <p><strong>Horário de Atendimento:</strong> Segunda a sexta, 9h às 18h</p>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mt-8">
                        <h4 class="font-semibold text-green-800 mb-2">✅ Aceitação:</h4>
                        <p class="text-green-700">
                            Ao criar uma conta e usar nossa plataforma, você confirma que leu, compreendeu e concorda em cumprir todos os termos e condições estabelecidos neste documento.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="w-full py-16 bg-blue-50">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                <h2 class="text-3xl font-bold heading-font text-gray-800 mb-4">
                    Pronto para aceitar os termos e começar?
                </h2>
                <p class="text-xl text-gray-600 mb-8">
                    Junte-se a milhares de estudantes que já confiam no Memorize Direito.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        :href="route('play.map')"
                        class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-blue-600 text-white font-bold text-xl rounded-lg border-4 border-blue-800 transition-all hover:transform hover:translate-y-1"
                        style="box-shadow: 0 6px 0 rgb(30, 58, 138);"
                        onmouseover="this.style.boxShadow = '0 4px 0 rgb(30, 58, 138)'"
                        onmouseout="this.style.boxShadow = '0 6px 0 rgb(30, 58, 138)'"
                    >
                        Aceitar e Começar
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
