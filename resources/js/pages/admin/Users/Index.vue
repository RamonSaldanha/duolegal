<template>
  <AppLayout title="Usuários">
    <div class="px-3 py-4 md:px-6 md:py-8">
      <div class="mx-auto w-full max-w-[1920px] space-y-6">
        <Breadcrumbs :breadcrumbs="breadcrumbs" class="mb-4" />

        <div class="grid gap-4 md:grid-cols-2">
          <Card class="shadow-none">
            <CardHeader class="pb-3">
              <CardDescription>Total de usuários</CardDescription>
              <CardTitle class="flex items-center gap-2 text-3xl font-bold">
                <Users class="h-6 w-6 text-primary" />
                {{ stats.total_users }}
              </CardTitle>
            </CardHeader>
          </Card>

          <Card class="shadow-none">
            <CardHeader class="pb-3">
              <CardDescription>Usuários pagantes ativos</CardDescription>
              <CardTitle class="flex items-center gap-2 text-3xl font-bold">
                <Crown class="h-6 w-6 text-amber-500" />
                {{ stats.paying_users }}
              </CardTitle>
            </CardHeader>
          </Card>
        </div>

        <Card class="shadow-none">
          <CardHeader>
            <CardTitle class="text-2xl font-bold">Crescimento nos últimos 20 dias</CardTitle>
            <CardDescription>
              Um gráfico com novos usuários por dia e novos pagantes por dia.
            </CardDescription>
          </CardHeader>

          <CardContent>
            <div class="space-y-4">
              <div class="flex flex-wrap items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full bg-primary" />
                  <span>Usuários</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full bg-amber-500" />
                  <span>Pagantes</span>
                </div>
              </div>

              <div class="w-full rounded-lg border bg-muted/20 p-3">
                <div v-if="chartData.length > 0" class="h-[200px] w-full">
                  <Line :data="lineChartData" :options="lineChartOptions" />
                </div>

                <div v-else class="flex h-[200px] items-center justify-center text-sm text-muted-foreground">
                  Não há dados suficientes para o período.
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card class="shadow-none">
          <CardHeader class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <CardTitle class="text-2xl font-bold">Usuários Cadastrados</CardTitle>
              <CardDescription>
                Visualize todos os usuários cadastrados, status de assinatura e vidas.
              </CardDescription>
            </div>

            <Button @click="exportBrevoContacts" class="gap-2">
              <Download class="h-4 w-4" />
              Exportar contatos (Brevo)
            </Button>
          </CardHeader>

          <CardContent>
            <div class="mb-6">
              <div class="relative max-w-md">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="searchQuery"
                  type="search"
                  placeholder="Pesquisar usuário..."
                  class="pl-10"
                />
              </div>
            </div>

            <div class="w-full overflow-x-auto rounded-lg border md:overflow-x-visible">
              <Table class="w-full min-w-[760px] md:min-w-0">
                <TableHeader>
                  <TableRow>
                    <TableHead>Nome</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Vidas</TableHead>
                    <TableHead>XP</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Admin</TableHead>
                    <TableHead>Data de Cadastro</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="user in filteredUsers" :key="user.id">
                    <TableCell>{{ user.name }}</TableCell>
                    <TableCell class="break-all">{{ user.email }}</TableCell>
                    <TableCell>
                      <div class="flex items-center">
                        <span v-if="user.is_premium" class="flex items-center">
                          <Infinity class="mr-1 h-4 w-4 text-primary" />
                          Ilimitadas
                        </span>
                        <span v-else class="flex items-center">
                          <Heart
                            class="mr-1 h-4 w-4 cursor-pointer text-red-500 transition-transform hover:scale-110"
                            @click="addLives(user)"
                            :title="'Clique para adicionar vidas a ' + user.name"
                          />
                          {{ user.lives }}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div class="flex items-center">
                        <span class="font-medium text-purple-500">{{ user.xp || 0 }} XP</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge v-if="user.is_premium" variant="default" class="bg-primary">Premium</Badge>
                      <Badge v-else variant="outline">Gratuito</Badge>
                    </TableCell>
                    <TableCell>
                      <Badge v-if="user.is_admin" variant="default" class="bg-amber-500">Sim</Badge>
                      <Badge v-else variant="outline">Não</Badge>
                    </TableCell>
                    <TableCell>{{ user.created_at }}</TableCell>
                  </TableRow>
                  <TableRow v-if="filteredUsers.length === 0">
                    <TableCell colspan="7" class="py-4 text-center">
                      <div class="text-muted-foreground">
                        <UserSearch class="mx-auto mb-3 h-12 w-12" />
                        <p>Nenhum usuário encontrado para "{{ searchQuery }}"</p>
                      </div>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
    <Toaster />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
  type ChartOptions,
} from 'chart.js';
import { Line } from 'vue-chartjs';
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, UserSearch, Heart, Infinity, Users, Crown, Download } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';
import Toaster from '@/components/ui/toast/Toaster.vue';

interface User {
  id: number;
  name: string;
  email: string;
  lives: number;
  xp: number;
  is_admin: boolean;
  is_premium: boolean;
  created_at: string;
}

interface Stats {
  total_users: number;
  paying_users: number;
}

interface DailyGrowth {
  date: string;
  label: string;
  users: number;
  paying_users: number;
}

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler);

interface BreadcrumbItem {
  title: string;
  href: string;
}

// Inicializa o toast para possíveis notificações
const { toast } = useToast();

const props = defineProps<{
  users?: User[];
  stats?: Stats;
  daily_growth?: DailyGrowth[];
}>();

// Define os breadcrumbs para navegação
const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Usuários',
    href: '/admin/users',
  },
];

// Campo de pesquisa
const searchQuery = ref('');

const stats = computed<Stats>(() => ({
  total_users: props.stats?.total_users ?? 0,
  paying_users: props.stats?.paying_users ?? 0,
}));

const chartData = computed(() => props.daily_growth ?? []);

const lineChartData = computed(() => ({
  labels: chartData.value.map((item) => item.label),
  datasets: [
    {
      label: 'Usuários',
      data: chartData.value.map((item) => item.users),
      borderColor: 'hsl(var(--primary))',
      backgroundColor: 'hsl(var(--primary))',
      pointBackgroundColor: 'hsl(var(--primary))',
      pointBorderColor: 'hsl(var(--background))',
      pointBorderWidth: 1,
      pointRadius: 3,
      pointHoverRadius: 5,
      tension: 0.3,
    },
    {
      label: 'Pagantes',
      data: chartData.value.map((item) => item.paying_users),
      borderColor: '#f59e0b',
      backgroundColor: '#f59e0b',
      pointBackgroundColor: '#f59e0b',
      pointBorderColor: 'hsl(var(--background))',
      pointBorderWidth: 1,
      pointRadius: 3,
      pointHoverRadius: 5,
      tension: 0.3,
    },
  ],
}));

const lineChartOptions: ChartOptions<'line'> = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      enabled: true,
      callbacks: {
        label(context) {
          const value = context.parsed.y ?? 0;
          return `${context.dataset.label}: ${value}`;
        },
      },
    },
  },
  scales: {
    x: {
      grid: {
        display: false,
      },
      ticks: {
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 10,
      },
    },
    y: {
      beginAtZero: true,
      ticks: {
        precision: 0,
      },
      grid: {
        color: 'hsl(var(--border))',
      },
    },
  },
};

// Filtragem de usuários baseada na pesquisa
const filteredUsers = computed(() => {
  if (!searchQuery.value || !props.users) {
    return props.users || [];
  }

  const query = searchQuery.value.toLowerCase();
  return props.users.filter(user =>
    user.name.toLowerCase().includes(query) ||
    user.email.toLowerCase().includes(query)
  );
});

// Função para adicionar vidas a um usuário
const addLives = (user: User) => {
  if (user.is_premium) {
    toast({
      title: "Usuário Premium",
      description: "Usuários premium já têm vidas ilimitadas.",
      variant: "default",
    });
    return;
  }

  router.post(`/admin/users/${user.id}/add-lives`, { lives: 1 }, {
    preserveScroll: true,
    onSuccess: () => {
      toast({
        title: "Vida adicionada!",
        description: `Vida adicionada com sucesso para ${user.name}`,
        variant: "default",
      });
    },
    onError: () => {
      toast({
        title: "Erro",
        description: "Não foi possível adicionar a vida. Tente novamente.",
        variant: "destructive",
      });
    }
  });
};

const exportBrevoContacts = () => {
  window.location.href = route('admin.users.export-brevo') as string;
};
</script>
