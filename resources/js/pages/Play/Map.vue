<!-- filepath: /c:/Users/ramon/Desktop/study/resources/js/Pages/Play/Map.vue -->
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Book, FileText, Bookmark, CheckCircle, Star } from 'lucide-vue-next';

const props = defineProps({
    phases: Array
});

// Agrupar fases por referência legal
const phasesByReference = computed(() => {
    const grouped = {};
    
    props.phases.forEach(phase => {
        if (!grouped[phase.reference_uuid]) {
            grouped[phase.reference_uuid] = {
                name: phase.reference_name,
                phases: []
            };
        }
        
        grouped[phase.reference_uuid].phases.push(phase);
    });
    
    return grouped;
});

// Função para definir cores baseadas no nível de dificuldade
const getDifficultyColor = (level) => {
    switch (level) {
        case 1: return 'bg-green-500';
        case 2: return 'bg-emerald-500';
        case 3: return 'bg-yellow-500';
        case 4: return 'bg-orange-500';
        case 5: return 'bg-red-500';
        default: return 'bg-blue-500';
    }
};

// Função para obter ícone baseado no número da fase
const getPhaseIcon = (phaseNumber) => {
    // Alternamos entre alguns ícones para variação
    const icons = [Book, FileText, Bookmark, Star, CheckCircle];
    return icons[(phaseNumber - 1) % icons.length];
};
</script>

<template>
  <Head title="Aprender Jogando" />

  <AppLayout>
    <div class="container py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Cabeçalho -->
        <div class="mb-8 text-center">
          <h1 class="text-3xl font-bold mb-2">Aprenda Legislação Brincando</h1>
          <p class="text-muted-foreground">Escolha uma fase e comece a aprender os artigos de lei de forma divertida.</p>
        </div>

        <!-- Mapa de fases -->
        <div class="space-y-16">
          <div 
            v-for="(referenceData, referenceUuid) in phasesByReference" 
            :key="referenceUuid" 
            class="relative"
          >
            <div class="flex justify-center mb-4">
              <div class="px-6 py-2 bg-primary/10 rounded-full">
                <h2 class="text-xl font-bold">{{ referenceData.name }}</h2>
              </div>
            </div>

            <!-- Container da trilha -->
            <div class="relative trail-container mx-auto">
              <!-- Linha vertical base (caminho principal) -->
              <div class="absolute left-1/2 top-0 bottom-0 w-3 bg-muted transform -translate-x-1/2 rounded-full -z-10"></div>
              
              <!-- Fases aproximadas no centro -->
              <div class="space-y-6">
                <div 
                  v-for="(phase, index) in referenceData.phases" 
                  :key="`phase-${phase.phase_number}`"
                  class="relative phase-item"
                >
                  <!-- Fase com posicionamento levemente alternado -->
                  <div class="flex items-center justify-center">
                    <!-- Fases ligeiramente à direita ou esquerda do centro -->
                    <div 
                      :class="`flex ${index % 2 === 0 ? 'justify-end ml-auto' : 'justify-start mr-auto'}`" 
                      style="width: 55%;"
                    >
                      <Link 
                        :href="route('play.phase', [phase.reference_uuid, phase.phase_number])"
                        class="relative group transition-transform duration-300 hover:scale-110"
                        :style="`margin-${index % 2 === 0 ? 'right' : 'left'}: -5px;`"
                      >
                        <!-- Bolinha da fase -->
                        <div 
                          :class="[
                            'w-16 h-16 rounded-full flex items-center justify-center phase-circle',
                            getDifficultyColor(phase.difficulty)
                          ]"
                        >
                          <component 
                            :is="getPhaseIcon(phase.phase_number)" 
                            class="w-6 h-6 text-white" 
                          />
                        </div>
                        
                        <!-- Badge com número da fase -->
                        <Badge class="absolute -top-2 -right-2 bg-primary">
                          {{ phase.phase_number }}
                        </Badge>
                        
                        <!-- Indicador de dificuldade -->
                        <div class="mt-1 flex justify-center">
                          <span 
                            v-for="i in 5" 
                            :key="i" 
                            class="w-1 h-1 mx-0.5 rounded-full"
                            :class="i <= phase.difficulty ? getDifficultyColor(phase.difficulty) : 'bg-muted'"
                          ></span>
                        </div>
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Instruções -->
        <div class="mt-12 p-6 bg-muted/50 rounded-lg text-center">
          <h3 class="text-xl font-bold mb-2">Como jogar?</h3>
          <p>Siga as fases e complete cada uma para dominar a legislação!</p>
          <p class="mt-2 text-sm text-muted-foreground">Escolha uma fase e pratique os artigos.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Estilo para as bolinhas sem degradê branco */
.phase-circle {
  box-shadow: inset 0 -5px 0 rgba(0, 0, 0, 0.2), 0 5px 6px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

/* Contentor da trilha - mais estreito para manter as fases próximas */
.trail-container {
  position: relative;
  padding: 10px 0;
  max-width: 220px; /* Mais estreito para aproximar as fases */
}

/* Item de fase */
.phase-item {
  position: relative;
}

/* Espaçamento vertical reduzido */
.space-y-6 > * + * {
  margin-top: 1.25rem; /* Menos espaço vertical */
}

/* Responsividade */
@media (max-width: 640px) {
  .trail-container {
    max-width: 200px !important; /* Ainda mais estreito no mobile */
  }
  
  .phase-item > div > div {
    width: 52% !important; /* Ligeiramente mais próximo do centro */
  }
  
  .phase-item > div > div a {
    margin-left: 0 !important;
    margin-right: 0 !important;
  }
  
  .phase-circle {
    width: 3.5rem !important;
    height: 3.5rem !important;
  }
  
  .phase-circle svg {
    width: 1.25rem !important;
    height: 1.25rem !important;
  }
}
</style>