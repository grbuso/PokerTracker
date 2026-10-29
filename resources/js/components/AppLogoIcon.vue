<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <span
        :class="['inline-flex shrink-0 items-center justify-center', className]"
        v-bind="$attrs"
    >
        <img
            src="/logo-poker.png"
            alt=""
            class="size-full object-contain"
            draggable="false"
        />
    </span>
</template>
