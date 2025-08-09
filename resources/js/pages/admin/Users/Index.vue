<template>
  <AppLayout title="Usuários">
    <div class="container py-4 md:py-8 px-3 md:px-4">
      <div class="w-full mx-auto">
        <Breadcrumbs :breadcrumbs="breadcrumbs" class="mb-4" />

        <Card>
          <CardHeader>
            <CardTitle class="text-2xl font-bold">Usuários Cadastrados</CardTitle>
            <CardDescription>
              Visualize todos os usuários cadastrados, status de assinatura e vidas
            </CardDescription>
          </CardHeader>

          <CardContent>
            <!-- Campo de pesquisa -->
            <div class="mb-6">
              <div class="relative">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="searchQuery"
                  type="search"
                  placeholder="Pesquisar usuário..."
                  class="pl-10"
                />
              </div>
            </div>

            <!-- Tabela de usuários -->
            <div class="overflow-x-auto">
              <Table>
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
                    <TableCell>{{ user.email }}</TableCell>
                    <TableCell>
                      <div class="flex items-center">
                        <span v-if="user.is_premium" class="flex items-center">
                          <Infinity class="h-4 w-4 mr-1 text-primary" />
                          Ilimitadas
                        </span>
                        <span v-else class="flex items-center">
                          <Heart 
                            class="h-4 w-4 mr-1 text-red-500 cursor-pointer hover:scale-110 transition-transform" 
                            @click="addLives(user)"
                            :title="'Clique para adicionar vidas a ' + user.name"
                          />
                          {{ user.lives }}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div class="flex items-center">
                        <span class="text-purple-500 font-medium">{{ user.xp || 0 }} XP</span>
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
                    <TableCell colspan="7" class="text-center py-4">
                      <div class="text-muted-foreground">
                        <UserSearch class="h-12 w-12 mx-auto mb-3" />
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
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Search, UserSearch, Heart, Infinity } from 'lucide-vue-next';
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

interface BreadcrumbItem {
  title: string;
  href: string;
}

// Inicializa o toast para possíveis notificações
const { toast } = useToast();

const props = defineProps<{
  users?: User[];
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
</script>
