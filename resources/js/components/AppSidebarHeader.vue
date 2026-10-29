<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        transparent?: boolean;
    }>(),
    {
        breadcrumbs: () => [],
        transparent: false,
    },
);
</script>

<template>
    <header
        :class="[
            'sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4',
            props.transparent
                ? 'border-transparent bg-transparent'
                : 'border-sidebar-border/70 bg-background/70 backdrop-blur-xl backdrop-saturate-150',
        ]"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
