<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const {
    breadcrumbs = [],
    transparentHeader = false,
    backdrop = false,
} = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    transparentHeader?: boolean;
    backdrop?: boolean;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :transparent-header="transparentHeader"
        :backdrop="backdrop"
    >
        <slot />
    </AppLayout>
</template>
