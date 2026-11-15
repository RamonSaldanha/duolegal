<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, Download, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    siteUrl: string;
    skillName: string;
    skillMarkdown: string | null;
    zipAvailable: boolean;
    tokens: { id: number; name: string; created_at: string | null; last_used_at: string | null }[];
    newToken: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Material de estudo', href: '/admin/study-materials' },
    { title: 'Instalar a skill', href: '#' },
];

const flash = computed(() => (usePage().props as any).flash ?? {});

// === Token ===
const tokenName = ref('');
const creating = ref(false);

function createToken() {
    creating.value = true;
    router.post(
        route('admin.study-materials.tokens.store'),
        { name: tokenName.value },
        { preserveScroll: true, onFinish: () => ((creating.value = false), (tokenName.value = '')) },
    );
}

function revokeToken(token: { id: number; name: string }) {
    if (confirm(`Revogar o token "${token.name}"?\n\nO agente que usa esse token para de funcionar.`)) {
        router.delete(route('admin.study-materials.tokens.destroy', token.id), { preserveScroll: true });
    }
}

const formatDate = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'nunca');

// === Copiar ===
const copied = ref<string | null>(null);
async function copy(id: string, text: string) {
    await navigator.clipboard.writeText(text);
    copied.value = id;
    setTimeout(() => {
        if (copied.value === id) copied.value = null;
    }, 2000);
}

// === Comandos ===
// O token recém-criado já entra nos comandos; depois que a página recarrega, volta o marcador.
const token = computed(() => props.newToken ?? 'COLE_O_TOKEN_AQUI');
const zip = `${props.skillName}.zip`;

const envUnix = computed(
    () => `echo 'export MEMORIZE_URL="${props.siteUrl}"' >> ~/.zshrc\necho 'export MEMORIZE_TOKEN="${token.value}"' >> ~/.zshrc\nsource ~/.zshrc`,
);
const envWindows = computed(() => `setx MEMORIZE_URL "${props.siteUrl}"\nsetx MEMORIZE_TOKEN "${token.value}"`);

const curlTest = computed(
    () => `curl -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" "$MEMORIZE_URL/api/v1/admin/study-materials/legislations"`,
);
const curlTestWindows = computed(
    () =>
        `curl.exe -H "Authorization: Bearer $env:MEMORIZE_TOKEN" -H "Accept: application/json" "$env:MEMORIZE_URL/api/v1/admin/study-materials/legislations"`,
);

const unzipUnix = (folder: string) => `mkdir -p ${folder} && unzip -o ~/Downloads/${zip} -d ${folder}`;
const unzipWindows = (folder: string) => `Expand-Archive -Force "$HOME\\Downloads\\${zip}" "$HOME\\${folder}"`;

const tools = computed(() => [
    {
        id: 'claude-code',
        label: 'Claude Code',
        intro: `Pasta pessoal: ~/.claude/skills/${props.skillName}/. Para valer só neste projeto, use .claude/skills/${props.skillName}/ na raiz dele.`,
        commands: [
            { label: 'macOS / Linux', text: unzipUnix('~/.claude/skills') },
            { label: 'Windows (PowerShell)', text: unzipWindows('.claude\\skills') },
        ],
        notes: [
            `Para chamar direto, comece a mensagem com /${props.skillName}. Um pedido em linguagem comum também aciona a skill.`,
            'Sessões na nuvem não leem a pasta pessoal: use a pasta do projeto, e o domínio do site precisa estar na lista de hosts permitidos do ambiente.',
        ],
    },
    {
        id: 'claude-app',
        label: 'Claude (claude.ai / app)',
        intro: `Envie o arquivo ${zip} em Configurações → Capacidades → Skills.`,
        commands: [],
        notes: [
            'O chat pode não ter acesso de rede ao site nem às variáveis de ambiente. Se a skill não conseguir chamar as rotas, use o Claude Code.',
        ],
    },
    {
        id: 'codex',
        label: 'Codex',
        intro: `Pasta pessoal: ~/.agents/skills/${props.skillName}/. Para valer só neste projeto, use .agents/skills/${props.skillName}/ na raiz dele.`,
        commands: [
            { label: 'macOS / Linux', text: unzipUnix('~/.agents/skills') },
            { label: 'Windows (PowerShell)', text: unzipWindows('.agents\\skills') },
        ],
        notes: [`Para chamar direto, digite $${props.skillName} na mensagem.`],
    },
    {
        id: 'opencode',
        label: 'OpenCode',
        intro: `Pasta pessoal: ~/.config/opencode/skills/${props.skillName}/. O OpenCode também lê ~/.claude/skills/ e ~/.agents/skills/, então quem já instalou para outra ferramenta não precisa repetir.`,
        commands: [
            { label: 'macOS / Linux', text: unzipUnix('~/.config/opencode/skills') },
            { label: 'Windows (PowerShell)', text: unzipWindows('.config\\opencode\\skills') },
        ],
        notes: [],
    },
]);
</script>

<template>
    <Head title="Instalar a skill" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container max-w-4xl space-y-6 p-4 md:p-8">
            <div>
                <h1 class="text-2xl font-bold">Instalar a skill</h1>
                <p class="mt-1 text-muted-foreground">
                    Quem escreve os resumos e as questões é um agente de IA rodando na sua máquina. Em cinco passos ele fica pronto para gravar o
                    material aqui no site.
                </p>
            </div>

            <div
                v-if="flash.success"
                class="rounded-lg bg-green-100 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
            >
                {{ flash.success }}
            </div>

            <!-- 1. Token -->
            <Card>
                <CardHeader>
                    <CardTitle>1. Gerar o token</CardTitle>
                    <CardDescription>O token dá ao agente acesso só às rotas de material de estudo, em seu nome.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="newToken"
                        class="space-y-2 rounded-lg border-2 border-green-300 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/40"
                    >
                        <p class="text-sm font-bold text-green-900 dark:text-green-200">
                            Copie agora: este token não será mostrado de novo. Os comandos abaixo já estão com ele.
                        </p>
                        <div class="flex items-center gap-2">
                            <code class="min-w-0 flex-1 break-all rounded bg-white px-3 py-2 text-sm dark:bg-gray-900">{{ newToken }}</code>
                            <Button type="button" variant="outline" @click="copy('token', newToken)">
                                <component :is="copied === 'token' ? Check : Copy" class="mr-2 h-4 w-4" />
                                {{ copied === 'token' ? 'Copiado' : 'Copiar' }}
                            </Button>
                        </div>
                    </div>

                    <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="createToken">
                        <Input v-model="tokenName" placeholder="Nome do token (ex.: notebook do escritório)" maxlength="60" />
                        <Button type="submit" class="shrink-0" :disabled="creating">Gerar token</Button>
                    </form>

                    <div v-if="tokens.length">
                        <p class="mb-2 text-sm font-medium">Tokens existentes</p>
                        <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <li v-for="item in tokens" :key="item.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ item.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Criado em {{ formatDate(item.created_at) }} · Último uso: {{ formatDate(item.last_used_at) }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    @click="revokeToken(item)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    <span class="sr-only">Revogar</span>
                                </Button>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Skill -->
            <Card>
                <CardHeader>
                    <CardTitle>2. Baixar a skill</CardTitle>
                    <CardDescription
                        >A skill é uma pasta com um arquivo SKILL.md, que ensina o agente a ler os dispositivos e gravar o material.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="!skillMarkdown" class="text-sm text-red-600">
                        A pasta da skill não foi encontrada neste servidor (.claude/skills/{{ skillName }}).
                    </p>
                    <div v-else class="flex flex-wrap gap-2">
                        <a v-if="zipAvailable" :href="route('admin.study-materials.skill')">
                            <Button type="button">
                                <Download class="mr-2 h-4 w-4" />
                                Baixar {{ zip }}
                            </Button>
                        </a>
                        <Button type="button" variant="outline" @click="copy('skill', skillMarkdown)">
                            <component :is="copied === 'skill' ? Check : Copy" class="mr-2 h-4 w-4" />
                            {{ copied === 'skill' ? 'Copiado' : 'Copiar o conteúdo do SKILL.md' }}
                        </Button>
                    </div>
                    <p v-if="skillMarkdown" class="text-sm text-muted-foreground">
                        Se preferir copiar, crie a pasta <code>{{ skillName }}</code> no lugar indicado no passo 4 e cole o conteúdo num arquivo
                        chamado <code>SKILL.md</code> dentro dela.
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Variáveis -->
            <Card>
                <CardHeader>
                    <CardTitle>3. Definir as variáveis de ambiente</CardTitle>
                    <CardDescription>
                        A skill lê o endereço do site em <code>MEMORIZE_URL</code> e o token em <code>MEMORIZE_TOKEN</code>. O token nunca é colado no
                        chat.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="block in [
                            { id: 'env-unix', label: 'macOS / Linux', text: envUnix },
                            { id: 'env-win', label: 'Windows (PowerShell)', text: envWindows },
                        ]"
                        :key="block.id"
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <p class="text-sm font-medium">{{ block.label }}</p>
                            <Button type="button" variant="ghost" size="sm" @click="copy(block.id, block.text)">
                                <component :is="copied === block.id ? Check : Copy" class="mr-1.5 h-3.5 w-3.5" />
                                {{ copied === block.id ? 'Copiado' : 'Copiar' }}
                            </Button>
                        </div>
                        <pre class="command">{{ block.text }}</pre>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        No macOS e no Linux, troque <code>~/.zshrc</code> por <code>~/.bashrc</code> se o seu terminal usa bash. Em qualquer sistema,
                        feche e abra o terminal (e o agente) depois de definir.
                    </p>
                </CardContent>
            </Card>

            <!-- 4. Ferramentas -->
            <Card>
                <CardHeader>
                    <CardTitle>4. Colocar a skill na ferramenta</CardTitle>
                    <CardDescription>Os comandos consideram que o {{ zip }} está na pasta Downloads.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Tabs default-value="claude-code">
                        <TabsList class="h-auto flex-wrap justify-start">
                            <TabsTrigger v-for="tool in tools" :key="tool.id" :value="tool.id">{{ tool.label }}</TabsTrigger>
                        </TabsList>
                        <TabsContent v-for="tool in tools" :key="tool.id" :value="tool.id" class="space-y-4 pt-2">
                            <p class="text-sm">{{ tool.intro }}</p>
                            <div v-for="command in tool.commands" :key="command.label">
                                <div class="mb-1 flex items-center justify-between">
                                    <p class="text-sm font-medium">{{ command.label }}</p>
                                    <Button type="button" variant="ghost" size="sm" @click="copy(`${tool.id}-${command.label}`, command.text)">
                                        <component :is="copied === `${tool.id}-${command.label}` ? Check : Copy" class="mr-1.5 h-3.5 w-3.5" />
                                        {{ copied === `${tool.id}-${command.label}` ? 'Copiado' : 'Copiar' }}
                                    </Button>
                                </div>
                                <pre class="command">{{ command.text }}</pre>
                            </div>
                            <ul v-if="tool.notes.length" class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                <li v-for="note in tool.notes" :key="note">{{ note }}</li>
                            </ul>
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>

            <!-- 5. Teste -->
            <Card>
                <CardHeader>
                    <CardTitle>5. Testar a conexão</CardTitle>
                    <CardDescription>O comando deve devolver a lista de leis em JSON. Se devolver 401 ou 403, confira o token.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="block in [
                            { id: 'curl-unix', label: 'macOS / Linux', text: curlTest },
                            { id: 'curl-win', label: 'Windows (PowerShell)', text: curlTestWindows },
                        ]"
                        :key="block.id"
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <p class="text-sm font-medium">{{ block.label }}</p>
                            <Button type="button" variant="ghost" size="sm" @click="copy(block.id, block.text)">
                                <component :is="copied === block.id ? Check : Copy" class="mr-1.5 h-3.5 w-3.5" />
                                {{ copied === block.id ? 'Copiado' : 'Copiar' }}
                            </Button>
                        </div>
                        <pre class="command">{{ block.text }}</pre>
                    </div>
                    <div>
                        <p class="text-sm font-medium">Depois, peça ao agente:</p>
                        <pre class="command">faça questões e resumos para a Constituição Federal</pre>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Ele continua de onde parou. O que ele grava entra publicado e já aparece no mapa; em Material de estudo você revisa e despublica se precisar.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
.command {
    @apply overflow-x-auto whitespace-pre rounded-md bg-gray-100 px-3 py-2 text-sm dark:bg-gray-800;
}
code {
    @apply rounded bg-gray-100 px-1 py-0.5 text-sm dark:bg-gray-800;
}
</style>
