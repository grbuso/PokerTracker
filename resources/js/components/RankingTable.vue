<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/composables/useInitials';
import { formatShortDate, toDateKey } from '@/lib/dates';

type TablePlayer = {
    id: number;
    name: string;
    avatar: string | null;
    pivot: {
        chips: number;
        eliminated_at: string | null;
    };
};

type TableSnapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

type Standing = {
    chips: number;
    rank: number;
    isEliminated: boolean;
    eliminatedAt: string | null;
};

type StandingRow = {
    id: number;
    name: string;
    avatar: string | null;
    rank: number;
    chips: number;
    isEliminated: boolean;
    eliminatedAt: string | null;
    previousRank: number | null;
    positionDelta: number | null;
    chipsDelta: number | null;
};

const props = defineProps<{
    players: TablePlayer[];
    snapshots: TableSnapshot[];
}>();

const chipFormatter = new Intl.NumberFormat('pt-BR');

const formatChips = (chips: number): string => chipFormatter.format(chips);

const days = computed(() =>
    [
        ...new Set(
            props.snapshots.map((snapshot) => toDateKey(snapshot.recorded_on)),
        ),
    ].sort(),
);

const selectedDay = ref<string | undefined>(undefined);

watch(
    days,
    (value, oldValue) => {
        if (!value.length) {
            selectedDay.value = undefined;

            return;
        }

        const current = selectedDay.value;
        const wasFollowingLast = !current || current === oldValue?.at(-1);

        if (wasFollowingLast || !current || !value.includes(current)) {
            selectedDay.value = value.at(-1);
        }
    },
    { immediate: true },
);

const selectedIndex = computed(() => {
    const index = selectedDay.value
        ? days.value.indexOf(selectedDay.value)
        : -1;

    return index === -1 ? Math.max(0, days.value.length - 1) : index;
});

const previousDay = computed<string | null>(() =>
    selectedIndex.value > 0
        ? (days.value[selectedIndex.value - 1] ?? null)
        : null,
);

const previousDayLabel = computed(() =>
    previousDay.value
        ? `Dia ${selectedIndex.value} · ${formatShortDate(previousDay.value)}`
        : null,
);

const playersById = computed(
    () => new Map(props.players.map((player) => [player.id, player])),
);

const chipsByDay = computed(() => {
    const result = new Map<string, Map<number, number>>();

    for (const snapshot of props.snapshots) {
        const day = toDateKey(snapshot.recorded_on);
        const daily = result.get(day) ?? new Map<number, number>();

        daily.set(snapshot.user_id, snapshot.chips);
        result.set(day, daily);
    }

    return result;
});

const eliminationDayFor = (player: TablePlayer): string | null =>
    player.pivot.eliminated_at ? toDateKey(player.pivot.eliminated_at) : null;

const survivorsForDay = (day: string): number => {
    const daily = chipsByDay.value.get(day);

    if (daily) {
        return [...daily.values()].filter((chips) => chips > 0).length;
    }

    return props.players.filter((player) => {
        const eliminationDay = eliminationDayFor(player);

        return eliminationDay === null || eliminationDay > day;
    }).length;
};

const finalPositionFor = (eliminationDay: string): number =>
    survivorsForDay(eliminationDay) + 1;

const standingsForDay = (day: string | null): Map<number, Standing> => {
    const standings = new Map<number, Standing>();

    if (!day) {
        return standings;
    }

    const daily = chipsByDay.value.get(day);
    const activeEntries = [...(daily?.entries() ?? [])].filter(
        ([, chips]) => chips > 0,
    );

    for (const [userId, chips] of activeEntries) {
        const higher = activeEntries.filter(
            ([, otherChips]) => otherChips > chips,
        ).length;

        standings.set(userId, {
            chips,
            rank: higher + 1,
            isEliminated: false,
            eliminatedAt: null,
        });
    }

    for (const player of props.players) {
        if (standings.has(player.id)) {
            continue;
        }

        const snapshotChips = daily?.get(player.id);
        const storedEliminationDay = eliminationDayFor(player);
        const eliminationDay =
            storedEliminationDay && storedEliminationDay <= day
                ? storedEliminationDay
                : snapshotChips !== undefined && snapshotChips <= 0
                  ? day
                  : null;

        if (!eliminationDay || eliminationDay > day) {
            continue;
        }

        standings.set(player.id, {
            chips: 0,
            rank: finalPositionFor(eliminationDay),
            isEliminated: true,
            eliminatedAt: eliminationDay,
        });
    }

    return standings;
};

const rows = computed<StandingRow[]>(() => {
    const day = selectedDay.value;

    if (!day) {
        return [];
    }

    const current = standingsForDay(day);
    const previous = standingsForDay(previousDay.value);

    return [...current.entries()]
        .map(([userId, standing]): StandingRow | null => {
            const player = playersById.value.get(userId);

            if (!player) {
                return null;
            }

            const previousStanding = previous.get(userId);

            return {
                id: userId,
                name: player.name,
                avatar: player.avatar,
                rank: standing.rank,
                chips: standing.chips,
                isEliminated: standing.isEliminated,
                eliminatedAt: standing.eliminatedAt,
                previousRank: previousStanding?.rank ?? null,
                positionDelta:
                    previousStanding === undefined
                        ? null
                        : previousStanding.rank - standing.rank,
                chipsDelta:
                    previousStanding === undefined
                        ? null
                        : standing.chips - previousStanding.chips,
            };
        })
        .filter((row): row is StandingRow => row !== null)
        .sort(
            (first, second) =>
                first.rank - second.rank ||
                Number(first.isEliminated) - Number(second.isEliminated) ||
                first.name.localeCompare(second.name, 'pt-BR'),
        );
});

const goPrevious = (): void => {
    if (previousDay.value) {
        selectedDay.value = previousDay.value;
    }
};

const goNext = (): void => {
    const next = days.value[selectedIndex.value + 1];

    if (next) {
        selectedDay.value = next;
    }
};

const positionChangeLabel = (row: StandingRow): string => {
    if (row.positionDelta === null) {
        return 'Sem posição no dia anterior';
    }

    if (row.positionDelta === 0) {
        return `Manteve a posição (${row.rank}º)`;
    }

    const amount = Math.abs(row.positionDelta);
    const positions = amount === 1 ? 'posição' : 'posições';

    return row.positionDelta > 0
        ? `Subiu ${amount} ${positions} (${row.previousRank}º → ${row.rank}º)`
        : `Desceu ${amount} ${positions} (${row.previousRank}º → ${row.rank}º)`;
};
</script>

<template>
    <div>
        <div
            v-if="!days.length"
            class="flex h-48 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
        >
            Nenhum dia registrado.
        </div>

        <div v-else class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        aria-label="Dia anterior"
                        :disabled="selectedIndex === 0"
                        @click="goPrevious"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>

                    <Select v-model="selectedDay">
                        <SelectTrigger size="sm" class="min-w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(day, index) in days"
                                :key="day"
                                :value="day"
                            >
                                Dia {{ index + 1 }} ·
                                {{ formatShortDate(day) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        aria-label="Próximo dia"
                        :disabled="selectedIndex === days.length - 1"
                        @click="goNext"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>

                <span
                    v-if="previousDayLabel"
                    class="text-[11px] text-muted-foreground"
                >
                    vs. {{ previousDayLabel }}
                </span>
            </div>

            <div v-if="rows.length" class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Pos.</th>
                            <th class="px-3 py-2 font-medium">Jogador</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Fichas
                            </th>
                            <th
                                class="px-3 py-2 text-right font-medium"
                                :title="
                                    previousDayLabel
                                        ? `Variação em relação a ${previousDayLabel}`
                                        : 'Sem dia anterior'
                                "
                            >
                                Variação
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="border-t transition-colors hover:bg-muted/30"
                            :class="
                                row.isEliminated
                                    ? 'bg-muted/20 text-muted-foreground'
                                    : ''
                            "
                        >
                            <td class="px-3 py-2.5 align-middle">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex w-8 justify-end text-base font-semibold tabular-nums"
                                        :title="
                                            row.isEliminated
                                                ? 'Posição final'
                                                : undefined
                                        "
                                    >
                                        {{ row.rank }}º
                                    </span>

                                    <span
                                        v-if="row.positionDelta === null"
                                        class="inline-flex items-center text-[11px] text-muted-foreground"
                                        title="Sem posição no dia anterior"
                                    >
                                        —
                                    </span>
                                    <span
                                        v-else-if="row.positionDelta > 0"
                                        class="inline-flex items-center gap-0.5 rounded bg-emerald-500/10 px-1 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400"
                                        :title="positionChangeLabel(row)"
                                    >
                                        <ArrowUp class="size-3" />
                                        {{ row.positionDelta }}
                                    </span>
                                    <span
                                        v-else-if="row.positionDelta < 0"
                                        class="inline-flex items-center gap-0.5 rounded bg-destructive/10 px-1 py-0.5 text-[11px] font-medium text-destructive"
                                        :title="positionChangeLabel(row)"
                                    >
                                        <ArrowDown class="size-3" />
                                        {{ Math.abs(row.positionDelta) }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center text-[11px] font-medium text-muted-foreground"
                                        :title="positionChangeLabel(row)"
                                    >
                                        =
                                    </span>
                                </div>
                            </td>

                            <td class="px-3 py-2.5">
                                <span
                                    class="inline-flex min-w-0 items-center gap-2"
                                >
                                    <Avatar
                                        class="size-7 overflow-hidden rounded-full"
                                    >
                                        <AvatarImage
                                            v-if="row.avatar"
                                            :src="row.avatar"
                                            :alt="row.name"
                                        />
                                        <AvatarFallback class="text-[10px]">
                                            {{ getInitials(row.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span class="inline-flex min-w-0 flex-col">
                                        <span class="truncate font-medium">
                                            {{ row.name }}
                                        </span>
                                        <span
                                            v-if="
                                                row.isEliminated &&
                                                row.eliminatedAt
                                            "
                                            class="text-[10px] leading-tight text-muted-foreground"
                                        >
                                            Eliminado em
                                            {{
                                                formatShortDate(
                                                    row.eliminatedAt,
                                                )
                                            }}
                                        </span>
                                    </span>
                                </span>
                            </td>

                            <td class="px-3 py-2.5 text-right tabular-nums">
                                {{ formatChips(row.chips) }}
                            </td>

                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <span
                                    v-if="row.chipsDelta === null"
                                    class="text-muted-foreground"
                                >
                                    —
                                </span>
                                <span
                                    v-else-if="row.chipsDelta > 0"
                                    class="text-emerald-700 dark:text-emerald-400"
                                >
                                    +{{ formatChips(row.chipsDelta) }}
                                </span>
                                <span
                                    v-else-if="row.chipsDelta < 0"
                                    class="text-destructive"
                                >
                                    -{{ formatChips(Math.abs(row.chipsDelta)) }}
                                </span>
                                <span v-else class="text-muted-foreground">
                                    0
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="flex h-40 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
            >
                Nenhum jogador registrado neste dia.
            </div>

            <p class="text-[11px] leading-relaxed text-muted-foreground">
                ↑ subiu · ↓ desceu em relação ao dia anterior. A coluna Variação
                mostra a diferença de fichas. Jogadores eliminados permanecem na
                classificação com a posição final.
            </p>
        </div>
    </div>
</template>
