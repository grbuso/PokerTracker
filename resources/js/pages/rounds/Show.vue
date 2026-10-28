<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Player = {
    id: number;
    name: string;
    pivot: { chips: number; eliminated_at: string | null };
};

type Snapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

const props = defineProps<{
    round: {
        id: number;
        starts_on: string | null;
        status: string;
        total_chips: number;
        players: Player[];
    };
    snapshots: Snapshot[];
}>();

const activePlayers = computed(() =>
    props.round.players.filter((player) => !player.pivot.eliminated_at),
);

const form = useForm({
    recorded_on: new Date().toLocaleDateString('sv-SE'),
    chips: {} as Record<number, number>,
});

const activePlayersKey = computed(() =>
    activePlayers.value
        .map((player) => `${player.id}:${player.pivot.chips}`)
        .join('|'),
);

watch(
    activePlayersKey,
    () => {
        form.chips = activePlayers.value.reduce<Record<number, number>>(
            (chips, player) => {
                chips[player.id] = player.pivot.chips;

                return chips;
            },
            {},
        );
    },
    { immediate: true },
);

const sum = computed(() =>
    activePlayers.value.reduce(
        (total, player) => total + Number(form.chips[player.id] ?? 0),
        0,
    ),
);

const days = computed(() => [
    ...new Set(props.snapshots.map((snapshot) => snapshot.recorded_on)),
]);

const cell = (userId: number, day: string): string => {
    const snapshot = props.snapshots.find(
        (item) => item.user_id === userId && item.recorded_on === day,
    );

    if (!snapshot) {
        return '—';
    }

    return snapshot.chips === 0 ? 'Eliminado' : String(snapshot.chips);
};

const formatDay = (day: string): string => {
    const [, month, date] = day.split('-');

    return month && date ? `${date}/${month}` : day;
};

const submit = () => {
    form.post(RoundController.storeSnapshot.url(props.round.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Rodada ${round.id}`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">
                    Rodada #{{ round.id }}
                    <Badge
                        :variant="
                            round.status === 'ativa' ? 'default' : 'outline'
                        "
                    >
                        {{ round.status }}
                    </Badge>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Total de fichas: {{ round.total_chips }}
                </p>
            </div>

            <Button as-child variant="outline">
                <Link :href="dashboard()">Voltar ao dashboard</Link>
            </Button>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Registrar o dia</CardTitle>
                    <CardDescription v-if="activePlayers.length">
                        Atualize as fichas dos jogadores que continuam na
                        rodada.
                    </CardDescription>
                    <CardDescription v-else>
                        Todos os jogadores desta rodada foram eliminados.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form
                        v-if="activePlayers.length"
                        class="space-y-4"
                        @submit.prevent="submit"
                    >
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="grid gap-2">
                                <Label for="recorded_on">Data</Label>
                                <input
                                    id="recorded_on"
                                    v-model="form.recorded_on"
                                    type="date"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="form.errors.recorded_on"
                                />
                            </div>
                            <p
                                class="pb-2 text-sm"
                                :class="
                                    sum === round.total_chips
                                        ? 'text-muted-foreground'
                                        : 'text-amber-600 dark:text-amber-400'
                                "
                            >
                                Soma: {{ sum }} / {{ round.total_chips }}
                            </p>
                        </div>

                        <div class="overflow-x-auto rounded-lg border">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-3 py-2 font-medium">
                                            Jogador
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            Fichas
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="player in round.players"
                                        :key="player.id"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2">
                                            {{ player.name }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-if="
                                                    !player.pivot.eliminated_at
                                                "
                                                v-model.number="
                                                    form.chips[player.id]
                                                "
                                                type="number"
                                                min="0"
                                                class="h-8 w-24 rounded-md border border-input bg-transparent px-2 text-right text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <Badge v-else variant="outline">
                                                Eliminado
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <InputError :message="form.errors.chips" />

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                Salvar o dia
                            </Button>
                        </div>
                    </form>

                    <p v-else class="text-sm text-muted-foreground">
                        Crie uma nova rodada no dashboard para continuar.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Histórico de snapshots</CardTitle>
                    <CardDescription>
                        Fichas de cada jogador por dia.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="days.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        Jogador
                                    </th>
                                    <th
                                        v-for="day in days"
                                        :key="day"
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        {{ formatDay(day) }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="player in round.players"
                                    :key="player.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2">
                                        {{ player.name }}
                                    </td>
                                    <td
                                        v-for="day in days"
                                        :key="day"
                                        class="px-3 py-2 text-right"
                                    >
                                        {{ cell(player.id, day) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum dia registrado ainda.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
