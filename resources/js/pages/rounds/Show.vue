<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Player = {
    id: number;
    name: string;
    pivot: { chips: number; eliminated_at: string | null };
};
type Snapshot = { user_id: number; recorded_on: string; chips: number };

const props = defineProps<{
    round: { id: number; status: string; total_chips: number; players: Player[] };
    snapshots: Snapshot[];
}>();

const activePlayers = computed(() => props.round.players.filter((p) => !p.pivot.eliminated_at));

const form = useForm({
    recorded_on: new Date().toLocaleDateString('sv-SE'), // AAAA-MM-DD, horário local
    chips: Object.fromEntries(activePlayers.value.map((p) => [p.id, p.pivot.chips])) as Record<number, number>,
});

const sum = computed(() => Object.values(form.chips).reduce((a, b) => a + Number(b || 0), 0));

const days = computed(() => [...new Set(props.snapshots.map((s) => s.recorded_on))]);

const cell = (userId: number, day: string) => {
    const s = props.snapshots.find((x) => x.user_id === userId && x.recorded_on === day);
    if (!s) return '-';
    return s.chips === 0 ? 'eliminado' : s.chips;
};

const submit = () => form.post(`/rounds/${props.round.id}/snapshots`);
</script>

<template>
    <Head :title="`Rodada ${round.id}`" />

    <div style="max-width: 700px; margin: 2rem auto">
        <h1>Rodada #{{ round.id }} ({{ round.status }})</h1>
        <p>Total de fichas: {{ round.total_chips }}</p>

        <h2>Registrar o dia</h2>
        <form @submit.prevent="submit">
            <p>
                <label>Data</label><br />
                <input type="date" v-model="form.recorded_on" />
                <span v-if="form.errors.recorded_on">{{ form.errors.recorded_on }}</span>
            </p>

            <p v-for="p in activePlayers" :key="p.id">
                <label>{{ p.name }}</label><br />
                <input type="number" min="0" v-model.number="form.chips[p.id]" />
            </p>

            <p v-if="sum !== round.total_chips" style="color: darkorange">
                Atenção: a soma é {{ sum }}, mas o total da rodada é {{ round.total_chips }}.
            </p>

            <button type="submit" :disabled="form.processing">Salvar o dia</button>
        </form>

        <h2>Histórico</h2>
        <table border="1" cellpadding="6" style="border-collapse: collapse">
            <thead>
                <tr>
                    <th>Jogador</th>
                    <th v-for="d in days" :key="d">{{ d }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="p in round.players" :key="p.id">
                    <td>{{ p.name }}</td>
                    <td v-for="d in days" :key="d">{{ cell(p.id, d) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>