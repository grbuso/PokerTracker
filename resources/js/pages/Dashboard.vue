<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Clock,
    Coins,
    Flame,
    Plus,
    Trophy,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PlayerController from '@/actions/App/Http/Controllers/Admin/PlayerController';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type ActiveRound = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    players_count: number;
    active_players_count: number;
    leader: {
        name: string;
        chips: number;
        tied: boolean;
        count: number;
    } | null;
    last_snapshot_on: string | null;
};

type Stats = {
    total_players: number;
    total_rounds: number;
    closed_rounds: number;
    last_snapshot_on: string | null;
};

type RoundSummary = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    status: string;
    players_count: number;
    winner: string | null;
    tie: boolean;
};

type RankingEntry = {
    id: number;
    name: string;
    wins: number;
    rounds_played: number;
    win_rate: number | null;
    streak: number;
};

const props = defineProps<{
    canManage: boolean;
    users: { id: number; name: string }[];
    stats: Stats;
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
        transparentHeader: true,
        backdrop: true,
    },
});

const createForm = useForm({
    total_chips: 1000,
    chips_per_player: 0,
    user_ids: props.users.map((user) => user.id),
});

const selectedPlayersCount = computed(() => createForm.user_ids.length);

const chipsPerPlayer = computed(() =>
    selectedPlayersCount.value > 0
        ? Math.floor(createForm.total_chips / selectedPlayersCount.value)
        : 0,
);

const chipsRemainder = computed(() =>
    selectedPlayersCount.value > 0
        ? createForm.total_chips % selectedPlayersCount.value
        : 0,
);

const canCreateRound = computed(
    () =>
        createForm.total_chips >= 1 &&
        selectedPlayersCount.value >= 2 &&
        chipsPerPlayer.value >= 1,
);

watch(
    () => [createForm.total_chips, createForm.user_ids.length],
    () => {
        createForm.chips_per_player = chipsPerPlayer.value;
    },
    { immediate: true },
);

const createOpen = ref(false);
const confirmCreateOpen = ref(false);

const openCreateDialog = () => {
    createOpen.value = true;
};

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            chips_per_player: chipsPerPlayer.value,
        }))
        .post(RoundController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createOpen.value = false;
            },
            onError: () => {
                createOpen.value = true;
            },
        });
};

const requestCreate = () => {
    if (!canCreateRound.value) {
        return;
    }

    if (props.activeRound) {
        createOpen.value = false;
        confirmCreateOpen.value = true;

        return;
    }

    submitCreate();
};

const confirmCreate = () => {
    confirmCreateOpen.value = false;
    submitCreate();
};

const showAllHistory = ref(false);
const visibleHistoryRounds = computed(() =>
    showAllHistory.value ? props.rounds : props.rounds.slice(0, 5),
);
const hasMoreHistoryRounds = computed(() => props.rounds.length > 5);

const leaderWins = computed(() =>
    Math.max(0, ...props.ranking.map((entry) => entry.wins)),
);

const leaderCount = computed(
    () =>
        props.ranking.filter(
            (entry) => entry.wins === leaderWins.value && entry.wins > 0,
        ).length,
);

const isLeader = (entry: RankingEntry): boolean =>
    leaderWins.value > 0 && entry.wins === leaderWins.value;

const chipFormatter = new Intl.NumberFormat('pt-BR');
const percentFormatter = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

const formatChips = (chips: number): string => chipFormatter.format(chips);
const formatPercent = (value: number): string =>
    `${percentFormatter.format(value)}%`;

const initialChipsPerPlayer = computed(() =>
    props.activeRound && props.activeRound.players_count > 0
        ? Math.floor(
              props.activeRound.total_chips / props.activeRound.players_count,
          )
        : null,
);

const roundStatusLabel = (status: string): string =>
    status === 'ativa' ? 'Em andamento' : 'Encerrada';

const roundStatusClass = (status: string): string =>
    status === 'ativa'
        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'border-border bg-muted text-muted-foreground';

const visitRound = (roundId: number) => {
    router.visit(RoundController.show.url(roundId));
};
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-5 overflow-x-auto p-4 md:p-6"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <p
                    class="text-xs font-medium tracking-[0.18em] text-primary uppercase"
                >
                    Dashboard
                </p>
                <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">
                    Visão geral
                </h1>
                <p class="text-sm text-muted-foreground tabular-nums">
                    {{ stats.total_rounds }} rodadas ·
                    {{ stats.closed_rounds }} encerradas
                </p>
            </div>

            <div v-if="canManage" class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="PlayerController.index.url()">
                        <Users class="size-4" />
                        Gerenciar jogadores
                    </Link>
                </Button>
                <Button type="button" @click="openCreateDialog">
                    <Plus class="size-4" />
                    Nova rodada
                </Button>
            </div>
        </header>

        <div
            v-if="!canManage"
            class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
        >
            <Users class="size-4 shrink-0" />
            Você está no modo jogador. Apenas administradores podem criar
            rodadas e lançar fichas.
        </div>

        <Card
            class="relative overflow-hidden border-primary/15 bg-linear-to-br from-primary/10 via-card to-card py-0"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-primary/10 blur-3xl"
                aria-hidden="true"
            />
            <CardContent
                class="relative grid gap-6 p-5 md:grid-cols-[1.35fr_1fr] md:p-6"
            >
                <div class="flex flex-col justify-between gap-5">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                :class="
                                    activeRound
                                        ? roundStatusClass('ativa')
                                        : 'border-border bg-muted text-muted-foreground'
                                "
                            >
                                {{
                                    activeRound ? 'Em andamento' : 'Mesa livre'
                                }}
                            </Badge>
                            <span
                                v-if="activeRound?.starts_on"
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <CalendarDays class="size-3.5" />
                                Início em {{ activeRound.starts_on }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-muted-foreground uppercase"
                            >
                                Rodada ativa
                            </p>
                            <h2
                                class="text-3xl font-semibold tracking-tight md:text-4xl"
                            >
                                {{
                                    activeRound
                                        ? `#${activeRound.id}`
                                        : 'Nenhuma'
                                }}
                            </h2>
                            <p class="max-w-xl text-sm text-muted-foreground">
                                <template v-if="activeRound">
                                    {{ activeRound.active_players_count }} de
                                    {{ activeRound.players_count }} jogadores
                                    ainda na mesa.
                                </template>
                                <template v-else-if="canManage">
                                    Abra a mesa para começar a registrar os dias
                                    da nova rodada.
                                </template>
                                <template v-else>
                                    Aguarde o administrador abrir a próxima
                                    rodada.
                                </template>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button v-if="activeRound" as-child>
                            <Link
                                :href="RoundController.show.url(activeRound.id)"
                            >
                                Abrir rodada
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            v-if="canManage && !activeRound"
                            type="button"
                            @click="openCreateDialog"
                        >
                            <Plus class="size-4" />
                            Criar rodada
                        </Button>
                        <span
                            v-if="activeRound"
                            class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Clock class="size-3.5" />
                            {{
                                activeRound.last_snapshot_on
                                    ? `Último lançamento em ${activeRound.last_snapshot_on}`
                                    : 'Nenhum lançamento registrado ainda'
                            }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 self-center">
                    <div
                        class="rounded-xl border bg-background/70 p-3 backdrop-blur-sm"
                    >
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <Coins class="size-3.5" />
                            <p
                                class="text-[11px] font-medium tracking-wide uppercase"
                            >
                                Fichas em jogo
                            </p>
                        </div>
                        <p
                            class="mt-1 truncate text-lg font-semibold tabular-nums"
                        >
                            {{
                                activeRound
                                    ? formatChips(activeRound.total_chips)
                                    : '—'
                            }}
                        </p>
                        <p
                            class="truncate text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{
                                initialChipsPerPlayer === null
                                    ? '—'
                                    : `${formatChips(initialChipsPerPlayer)} iniciais por jogador`
                            }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border bg-background/70 p-3 backdrop-blur-sm"
                    >
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <Trophy class="size-3.5" />
                            <p
                                class="text-[11px] font-medium tracking-wide uppercase"
                            >
                                Líder da rodada
                            </p>
                        </div>
                        <p class="mt-1 truncate text-lg font-semibold">
                            {{ activeRound?.leader?.name ?? '—' }}
                        </p>
                        <p
                            v-if="activeRound?.leader"
                            class="truncate text-[11px] text-muted-foreground"
                        >
                            {{ formatChips(activeRound.leader.chips) }} fichas
                            <span v-if="activeRound.leader.tied">
                                · {{ activeRound.leader.count }} empatados
                            </span>
                        </p>
                        <p
                            v-else
                            class="truncate text-[11px] text-muted-foreground"
                        >
                            {{
                                activeRound
                                    ? 'Sem jogadores ativos'
                                    : 'Sem rodada ativa'
                            }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <Card id="ranking" class="scroll-mt-24">
                <CardHeader>
                    <CardTitle>Ranking de vitórias</CardTitle>
                    <CardDescription v-if="leaderWins > 0 && leaderCount > 1">
                        Empate no topo: {{ leaderCount }} jogadores com
                        {{ leaderWins }}
                        {{ leaderWins === 1 ? 'vitória' : 'vitórias' }}.
                    </CardDescription>
                    <CardDescription v-else>
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
                                class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="px-3 py-2.5 font-medium">#</th>
                                    <th class="px-3 py-2.5 font-medium">
                                        Jogador
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center font-medium"
                                    >
                                        Vitórias
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center font-medium"
                                    >
                                        Rodadas
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center font-medium"
                                    >
                                        Aproveitamento
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center font-medium"
                                    >
                                        Sequência
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, index) in ranking"
                                    :key="entry.id"
                                    class="border-t transition-colors hover:bg-muted/30"
                                    :class="
                                        isLeader(entry) ? 'bg-primary/5' : ''
                                    "
                                >
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="flex size-6 items-center justify-center rounded-full text-xs font-semibold"
                                            :class="
                                                isLeader(entry)
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                        >
                                            {{ index + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="inline-flex items-center gap-1.5 font-medium"
                                        >
                                            <Trophy
                                                v-if="isLeader(entry)"
                                                class="size-3.5 text-primary"
                                            />
                                            {{ entry.name }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        {{ entry.wins }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        {{ entry.rounds_played }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-center text-xs tabular-nums"
                                    >
                                        {{
                                            entry.win_rate === null
                                                ? '—'
                                                : formatPercent(entry.win_rate)
                                        }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span
                                            v-if="entry.streak > 0"
                                            class="inline-flex items-center gap-1 font-medium"
                                        >
                                            <Flame
                                                v-if="entry.streak >= 2"
                                                class="size-3.5 text-amber-500"
                                            />
                                            {{ entry.streak }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                        >
                                            –
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Sem jogadores cadastrados ainda.
                    </p>
                </CardContent>
            </Card>

            <Card id="historico" class="scroll-mt-24">
                <CardHeader>
                    <CardTitle>Histórico de rodadas</CardTitle>
                    <CardDescription>
                        Consulte informações de rodadas passadas.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <template v-if="rounds.length">
                        <div class="overflow-x-auto rounded-lg border">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th
                                            class="px-3 py-2.5 text-left font-medium"
                                        >
                                            Rodada
                                        </th>
                                        <th
                                            class="px-3 py-2.5 text-center font-medium"
                                        >
                                            Início
                                        </th>
                                        <th
                                            class="px-3 py-2.5 text-center font-medium"
                                        >
                                            Jogadores
                                        </th>
                                        <th
                                            class="px-3 py-2.5 text-center font-medium"
                                        >
                                            Vencedor
                                        </th>
                                        <th
                                            class="px-3 py-2.5 text-center font-medium"
                                        >
                                            Status
                                        </th>
                                        <th class="px-3 py-2.5"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="round in visibleHistoryRounds"
                                        :key="round.id"
                                        class="cursor-pointer border-t transition-colors hover:bg-muted/30 focus-visible:bg-muted/50 focus-visible:outline-none"
                                        tabindex="0"
                                        role="link"
                                        @click="visitRound(round.id)"
                                        @keydown.enter="visitRound(round.id)"
                                    >
                                        <td
                                            class="px-3 py-2.5 text-left font-medium"
                                        >
                                            #{{ round.id }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            {{ round.starts_on ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            {{ round.players_count }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span
                                                v-if="round.winner"
                                                class="inline-flex items-center gap-1.5"
                                            >
                                                <Trophy
                                                    class="size-3.5 text-primary"
                                                />
                                                <span class="max-w-36 truncate">
                                                    {{ round.winner }}
                                                </span>
                                            </span>
                                            <span
                                                v-else-if="round.tie"
                                                class="text-muted-foreground"
                                            >
                                                Empate
                                            </span>
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                —
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <Badge
                                                variant="outline"
                                                :class="
                                                    roundStatusClass(
                                                        round.status,
                                                    )
                                                "
                                            >
                                                {{
                                                    roundStatusLabel(
                                                        round.status,
                                                    )
                                                }}
                                            </Badge>
                                        </td>
                                        <td class="px-3 py-2.5 text-right">
                                            <ChevronRight
                                                class="ml-auto size-4 text-muted-foreground"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-if="hasMoreHistoryRounds"
                            class="mt-3 flex justify-center"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :aria-expanded="showAllHistory"
                                @click="showAllHistory = !showAllHistory"
                            >
                                <component
                                    :is="
                                        showAllHistory ? ChevronUp : ChevronDown
                                    "
                                />
                                {{ showAllHistory ? 'Ver menos' : 'Ver mais' }}
                            </Button>
                        </div>
                    </template>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhuma rodada ainda. Que coragem a de vocês.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Dialog v-if="canManage" v-model:open="createOpen">
            <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Nova rodada</DialogTitle>
                    <DialogDescription v-if="activeRound">
                        A nova rodada encerra a #{{ activeRound.id }}
                        automaticamente.
                    </DialogDescription>
                    <DialogDescription v-else>
                        Defina o total de fichas e quem senta à mesa.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="requestCreate">
                    <div
                        class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
                    >
                        <div class="grid gap-2">
                            <Label for="total_chips">Total de fichas</Label>
                            <input
                                id="total_chips"
                                v-model.number="createForm.total_chips"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError
                                :message="createForm.errors.total_chips"
                            />
                        </div>

                        <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span
                                    v-if="selectedPlayersCount >= 2"
                                    class="font-medium"
                                >
                                    {{ chipsPerPlayer }} por jogador
                                </span>
                                <span v-else class="text-muted-foreground">
                                    Escolha pelo menos 2 jogadores
                                </span>
                                <span class="text-muted-foreground">
                                    {{ selectedPlayersCount }}
                                    {{
                                        selectedPlayersCount === 1
                                            ? 'selecionado'
                                            : 'selecionados'
                                    }}
                                </span>
                            </div>
                            <p
                                v-if="chipsRemainder"
                                class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                            >
                                {{ chipsRemainder }}
                                {{
                                    chipsRemainder === 1
                                        ? 'ficha vai'
                                        : 'fichas vão'
                                }}
                                sobrar sem dono.
                            </p>
                            <InputError
                                :message="createForm.errors.chips_per_player"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Quem vai jogar?</Label>
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

                    <DialogFooter>
                        <DialogClose as-child>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="createForm.processing || !canCreateRound"
                        >
                            {{ activeRound ? 'Continuar' : 'Criar rodada' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-if="activeRound" v-model:open="confirmCreateOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Encerrar a rodada #{{ activeRound.id }}?
                    </DialogTitle>
                    <DialogDescription>
                        Isso encerra a rodada atual e cria a nova com os
                        jogadores selecionados. Continuar?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button @click="confirmCreate">Encerrar e criar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
