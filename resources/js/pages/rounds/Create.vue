<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

defineProps<{ users: { id: number; name: string }[] }>();

const form = useForm({
    total_chips: 1000,
    chips_per_player: 0,
    user_ids: [] as number[],
});

// recalcula as fichas por jogador quando muda o total ou a quantidade de jogadores
watch(
    () => [form.total_chips, form.user_ids.length],
    () => {
        form.chips_per_player = form.user_ids.length
            ? Math.floor(form.total_chips / form.user_ids.length)
            : 0;
    },
);

const submit = () => form.post('/rounds');
</script>

<template>
    <Head title="Nova rodada" />

    <form @submit.prevent="submit" style="max-width: 400px; margin: 2rem auto">
        <h1>Nova rodada</h1>

        <p>
            <label>Total de fichas</label><br />
            <input type="number" v-model.number="form.total_chips" />
            <span v-if="form.errors.total_chips">{{ form.errors.total_chips }}</span>
        </p>

        <p>Quem vai jogar:</p>
        <label v-for="u in users" :key="u.id" style="display: block">
            <input type="checkbox" :value="u.id" v-model="form.user_ids" />
            {{ u.name }}
        </label>
        <span v-if="form.errors.user_ids">{{ form.errors.user_ids }}</span>

        <p>
            <label>Fichas por jogador (você pode ajustar)</label><br />
            <input type="number" v-model.number="form.chips_per_player" />
        </p>

        <button type="submit" :disabled="form.processing">Criar rodada</button>
    </form>
</template>