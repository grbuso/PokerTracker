<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import PageBackdrop from '@/components/PageBackdrop.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    transparentHeader?: boolean;
    backdrop?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    transparentHeader: false,
    backdrop: false,
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="relative isolate min-w-0 overflow-x-clip"
        >
            <PageBackdrop v-if="backdrop" />
            <AppSidebarHeader
                :breadcrumbs="breadcrumbs"
                :transparent="transparentHeader"
            />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
