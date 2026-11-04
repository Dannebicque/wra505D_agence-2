<script setup>
import { defineProps } from 'vue';
import { useRouter } from 'vue-router';
import { EmptyState } from '@components';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

const props = defineProps({
  data: {
    type: Object,
    default: () => ({ unreadCount: 0, messages: [] }),
  },
  widget: {
    type: Object,
    default: () => ({}),
  },
});

const router = useRouter();

const goToMessage = (id) => {
  if (id) {
    router.push(`/message/${id}`);
  } else {
    router.push('/message');
  }
};
</script>

<template>
  <div class="flex flex-col h-full justify-between">
    <div>
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <i class="pi pi-inbox text-primary text-lg" aria-hidden="true" />
          <span class="font-bold text-color text-sm">Messagerie interne</span>
        </div>
        <Badge
          v-if="props.data?.unreadCount > 0"
          :value="`${props.data.unreadCount} non lu${props.data.unreadCount > 1 ? 's' : ''}`"
          severity="danger"
        />
        <span v-else class="text-xs text-muted-color">À jour</span>
      </div>

      <div v-if="!props.data?.messages || props.data.messages.length === 0" class="py-2">
        <EmptyState
          compact
          title="Aucun nouveau message"
          description="Votre boîte de réception est à jour."
          icon="pi pi-check-circle"
          color="emerald"
        />
      </div>

      <ul v-else class="divide-y divide-surface-100 dark:divide-surface-800 m-0 p-0 list-none">
        <li
          v-for="msg in props.data.messages"
          :key="msg.id"
          class="py-2.5 flex items-start justify-between gap-2 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800/50 rounded px-1.5 transition-colors"
          @click="goToMessage(msg.id)"
        >
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <span
                v-if="!msg.isLu"
                class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"
              />
              <span
                v-if="msg.prioritaire"
                class="text-[10px] font-bold text-color bg-highlight px-1 rounded shrink-0"
              >
                Urgent
              </span>
              <p
                class="text-xs text-color truncate m-0"
                :class="{ 'font-bold': !msg.isLu, 'font-medium': msg.isLu }"
              >
                {{ msg.sujet }}
              </p>
            </div>
            <p class="text-[11px] text-muted-color m-0 truncate">
              {{ msg.expediteur }}
            </p>
          </div>
          <span class="text-[10px] text-muted-color shrink-0 pt-0.5">
            {{ msg.createdAt }}
          </span>
        </li>
      </ul>
    </div>

    <div class="pt-3 border-t border-surface-100 dark:border-surface-800 mt-2">
      <Button
        label="Consulter tous les messages"
        icon="pi pi-arrow-right"
        icon-pos="right"
        severity="secondary"
        size="small"
        text
        class="w-full text-xs justify-center"
        @click="goToMessage(null)"
      />
    </div>
  </div>
</template>
