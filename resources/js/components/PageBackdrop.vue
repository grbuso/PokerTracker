<script setup lang="ts">
const glow =
    'radial-gradient(ellipse 75% 55% at 50% 0%, color-mix(in srgb, var(--primary) 14%, transparent) 0%, color-mix(in srgb, var(--primary) 11%, transparent) 20%, color-mix(in srgb, var(--primary) 7%, transparent) 38%, color-mix(in srgb, var(--primary) 3%, transparent) 55%, color-mix(in srgb, var(--primary) 1%, transparent) 68%, transparent 80%)';
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
    >
        <div class="absolute inset-0" :style="{ background: glow }" />

        <div
            class="absolute inset-0 [background-image:radial-gradient(var(--muted-foreground)_0.7px,transparent_0.7px)] [mask-image:linear-gradient(to_bottom,black,transparent_68%)] [background-size:20px_20px] opacity-[0.14] dark:opacity-[0.10]"
        />
    </div>
</template>
