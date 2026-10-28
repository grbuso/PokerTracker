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
    chips: number;
    eliminated_at: string | null;
};

type Snapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

type ActiveRound = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    players: Player[];
    snapshots: Snapshot[];
};

type RoundSummary = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    status: string;
    players_count: number;
    winner: string | null;
};

type RankingEntry = {
    id: number;
    name: string;
    wins: number;
    rounds_played: number;
    current_chips: number | null;
};

const props = defineProps<{
    users: { id: number; name: string }[];
    activeRound: ActiveRound | null;
    rounds: RoundSummary[];
    ranking: RankingEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const activePlayers = computed(
    () =>
        props.activeRound?.players.filter((player) => !player.eliminated_at) ??
        [],
);

const snapshotForm = useForm({
    recorded_on: new Date().toLocaleDateString('sv-SE'),
    chips: {} as Record<number, number>,
});

const activePlayersKey = computed(() =>
    activePlayers.value
        .map((player) => `${player.id}:${player.chips}`)
        .join('|'),
);

watch(
    activePlayersKey,
    () => {
        snapshotForm.chips = activePlayers.value.reduce<Record<number, number>>(
            (chips, player) => {
                chips[player.id] = player.chips;

                return chips;
            },
            {},
        );
    },
    { immediate: true },
);

const snapshotTotal = computed(() =>
    activePlayers.value.reduce(
        (total, player) => total + Number(snapshotForm.chips[player.id] ?? 0),
        0,
    ),
);

const snapshotDays = computed(() => [
    ...new Set(
        (props.activeRound?.snapshots ?? []).map(
            (snapshot) => snapshot.recorded_on,
        ),
    ),
]);

const snapshotCell = (userId: number, day: string): string => {
    const snapshot = props.activeRound?.snapshots.find(
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

const submitSnapshot = () => {
    if (!props.activeRound) {
        return;
    }

    snapshotForm.post(RoundController.storeSnapshot.url(props.activeRound.id), {
        preserveScroll: true,
    });
};

const createForm = useForm({
    total_chips: 1000,
    chips_per_player: 0,
    user_ids: [] as number[],
});

watch(
    () => [createForm.total_chips, createForm.user_ids.length],
    () => {
        createForm.chips_per_player = createForm.user_ids.length
            ? Math.floor(createForm.total_chips / createForm.user_ids.length)
            : 0;
    },
);

const submitCreate = () => {
    createForm.post(RoundController.store.url(), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const closedRoundsCount = computed(
    () => props.rounds.filter((round) => round.status === 'encerrada').length,
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Rodada ativa</CardDescription>
                    <CardTitle class="text-2xl">
                        {{ activeRound ? `#${activeRound.id}` : 'Nenhuma' }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    <span v-if="activeRound">
                        {{ activeRound.starts_on }} ·
                        {{ activeRound.total_chips }} fichas
                    </span>
                    <span v-else>Crie uma rodada para começar.</span>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Jogadores ativos</CardDescription>
                    <CardTitle class="text-2xl">
                        {{ activePlayers.length }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Quem chegar a zero está eliminado.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Rodadas encerradas</CardDescription>
                    <CardTitle class="text-2xl">
                        {{ closedRoundsCount }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {{ rounds.length }} rodadas registradas.
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Atualizar rodada atual</CardTitle>
                    <CardDescription v-if="activeRound">
                        Registre as fichas de hoje. Quem ficar com zero sai.
                    </CardDescription>
                    <CardDescription v-else>
                        Nenhuma rodada ativa no momento.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form
                        v-if="activeRound"
                        class="space-y-4"
                        @submit.prevent="submitSnapshot"
                    >
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="grid gap-2">
                                <Label for="recorded_on">Data</Label>
                                <input
                                    id="recorded_on"
                                    v-model="snapshotForm.recorded_on"
                                    type="date"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="snapshotForm.errors.recorded_on"
                                />
                            </div>
                            <p
                                class="pb-2 text-sm"
                                :class="
                                    snapshotTotal === activeRound.total_chips
                                        ? 'text-muted-foreground'
                                        : 'text-amber-600 dark:text-amber-400'
                                "
                            >
                                Soma: {{ snapshotTotal }} /
                                {{ activeRound.total_chips }}
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
                                        <th class="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="player in activeRound.players"
                                        :key="player.id"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2">
                                            {{ player.name }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-if="!player.eliminated_at"
                                                v-model.number="
                                                    snapshotForm.chips[
                                                        player.id
                                                    ]
                                                "
                                                type="number"
                                                min="0"
                                                class="h-8 w-24 rounded-md border border-input bg-transparent px-2 text-right text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                —
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge
                                                :variant="
                                                    player.eliminated_at
                                                        ? 'outline'
                                                        : 'secondary'
                                                "
                                            >
                                                {{
                                                    player.eliminated_at
                                                        ? 'Eliminado'
                                                        : 'Ativo'
                                                }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <InputError :message="snapshotForm.errors.chips" />

                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Quem ficar com zero é eliminado automaticamente.
                            </p>
                            <Button
                                type="submit"
                                :disabled="
                                    snapshotForm.processing ||
                                    activePlayers.length === 0
                                "
                            >
                                Salvar dia
                            </Button>
                        </div>

                        <div
                            v-if="snapshotDays.length"
                            class="space-y-2 border-t pt-4"
                        >
                            <p class="text-sm font-medium">
                                Snapshots da rodada
                            </p>
                            <div class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead
                                        class="bg-muted/50 text-left text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="px-3 py-2 font-medium">
                                                Jogador
                                            </th>
                                            <th
                                                v-for="day in snapshotDays"
                                                :key="day"
                                                class="px-3 py-2 text-right font-medium"
                                            >
                                                {{ formatDay(day) }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="player in activeRound.players"
                                            :key="player.id"
                                            class="border-t"
                                        >
                                            <td class="px-3 py-2">
                                                {{ player.name }}
                                            </td>
                                            <td
                                                v-for="day in snapshotDays"
                                                :key="day"
                                                class="px-3 py-2 text-right"
                                            >
                                                {{
                                                    snapshotCell(player.id, day)
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>

                    <p v-else class="text-sm text-muted-foreground">
                        Crie a próxima rodada ao lado.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Criar rodada</CardTitle>
                    <CardDescription>
                        Ao criar uma nova rodada, a rodada ativa é encerrada.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div class="grid gap-2">
                            <Label for="total_chips">Total de fichas</Label>
                            <input
                                id="total_chips"
                                v-model.number="createForm.total_chips"
                                type="number"
                                min="1"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError
                                :message="createForm.errors.total_chips"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="chips_per_player">
                                    Fichas por jogador
                                </Label>
                                <input
                                    id="chips_per_player"
                                    v-model.number="createForm.chips_per_player"
                                    type="number"
                                    min="1"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="
                                        createForm.errors.chips_per_player
                                    "
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Jogadores</Label>
                                <p class="text-sm text-muted-foreground">
                                    {{ createForm.user_ids.length }}
                                    selecionados
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label>Quem vai jogar</Label>
                            <div
                                class="grid max-h-56 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2"
                            >
                                <label
                                    v-for="user in users"
                                    :key="user.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        v-model="createForm.user_ids"
                                        :value="user.id"
                                        type="checkbox"
                                        class="size-4 rounded border-input accent-primary"
                                    />
                                    {{ user.name }}
                                </label>
                            </div>
                            <InputError :message="createForm.errors.user_ids" />
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="createForm.processing"
                            >
                                Criar rodada
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Histórico de rodadas</CardTitle>
                    <CardDescription>
                        Todas as rodadas registradas.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="rounds.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        Rodada
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Início
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Jogadores
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Vencedor
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="round in rounds"
                                    :key="round.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 font-medium">
                                        #{{ round.id }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ round.starts_on ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ round.players_count }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ round.winner ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Badge
                                            :variant="
                                                round.status === 'ativa'
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                        >
                                            {{ round.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    RoundController.show.url(
                                                        round.id,
                                                    )
                                                "
                                            >
                                                Ver
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhuma rodada criada ainda.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ranking de vitórias</CardTitle>
                    <CardDescription>
                        Vitórias em rodadas encerradas.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="ranking.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">#</th>
                                    <th class="px-3 py-2 font-medium">
                                        Jogador
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Vitórias
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Rodadas
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Fichas atuais
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, index) in ranking"
                                    :key="entry.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 text-muted-foreground">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="px-3 py-2">{{ entry.name }}</td>
                                    <td class="px-3 py-2 text-right">
                                        {{ entry.wins }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ entry.rounds_played }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ entry.current_chips ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum jogador cadastrado.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
