<script setup lang="ts">
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { formatShortDate, toDateKey } from '@/lib/dates';

type ChartPlayer = {
    id: number;
    name: string;
    avatar: string | null;
};

type ChartSnapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

type RankPoint = {
    x: number;
    y: number;
    day: string;
    rank: number;
    chips: number;
};

type HoveredPoint = RankPoint & {
    playerId: number;
    name: string;
    color: string;
};

const props = defineProps<{
    players: ChartPlayer[];
    snapshots: ChartSnapshot[];
}>();

const width = 720;
const height = 220;
const padding = { top: 12, right: 12, bottom: 26, left: 44 };

const colors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-6)',
    'var(--chart-7)',
    'var(--chart-8)',
    'var(--chart-9)',
];

const chipFormatter = new Intl.NumberFormat('pt-BR');

const formatChips = (chips: number): string => chipFormatter.format(chips);

const days = computed(() =>
    [
        ...new Set(
            props.snapshots.map((snapshot) => toDateKey(snapshot.recorded_on)),
        ),
    ].sort(),
);

const chartPlayers = computed(() =>
    props.players.filter((player) =>
        props.snapshots.some(
            (snapshot) => snapshot.user_id === player.id && snapshot.chips > 0,
        ),
    ),
);

/**
 * rank por dia: 1 = quem tem mais fichas. Empates compartilham a posição
 * (ex.: dois líderes = 1º, 1º; o próximo = 3º). Jogadores eliminados
 * (0 fichas) não entram no ranking daquele dia.
 */
const ranks = computed(() => {
    const byDay = new Map<string, ChartSnapshot[]>();

    for (const snapshot of props.snapshots) {
        if (snapshot.chips <= 0) {
            continue;
        }

        const day = toDateKey(snapshot.recorded_on);
        const entries = byDay.get(day) ?? [];

        entries.push(snapshot);
        byDay.set(day, entries);
    }

    const result = new Map<string, Map<number, number>>();

    for (const [day, entries] of byDay) {
        const daily = new Map<number, number>();

        for (const entry of entries) {
            const higher = entries.filter(
                (other) => other.chips > entry.chips,
            ).length;

            daily.set(entry.user_id, higher + 1);
        }

        result.set(day, daily);
    }

    return result;
});

const maxRank = computed(() => Math.max(1, props.players.length));

const rankTicks = computed(() => {
    const step = maxRank.value <= 10 ? 1 : Math.ceil(maxRank.value / 8);
    const ticks: number[] = [];

    for (let rank = 1; rank <= maxRank.value; rank += step) {
        ticks.push(rank);
    }

    if (ticks.at(-1) !== maxRank.value) {
        ticks.push(maxRank.value);
    }

    return ticks;
});

const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const xFor = (index: number): number => {
    if (days.value.length === 1) {
        return padding.left + plotWidth / 2;
    }

    return padding.left + (index / (days.value.length - 1)) * plotWidth;
};

const yForRank = (rank: number): number =>
    maxRank.value <= 1
        ? padding.top + plotHeight / 2
        : padding.top + ((rank - 1) / (maxRank.value - 1)) * plotHeight;

const series = computed(() => {
    const dayIndexes = new Map(days.value.map((day, index) => [day, index]));

    return chartPlayers.value.map((player, playerIndex) => {
        const points = props.snapshots
            .filter(
                (snapshot) =>
                    snapshot.user_id === player.id && snapshot.chips > 0,
            )
            .sort((first, second) =>
                toDateKey(first.recorded_on).localeCompare(
                    toDateKey(second.recorded_on),
                ),
            )
            .map((snapshot): RankPoint | null => {
                const day = toDateKey(snapshot.recorded_on);
                const rank = ranks.value.get(day)?.get(player.id) ?? null;

                if (rank === null) {
                    return null;
                }

                return {
                    x: xFor(dayIndexes.get(day) ?? 0),
                    y: yForRank(rank),
                    day,
                    rank,
                    chips: snapshot.chips,
                };
            })
            .filter((point): point is RankPoint => point !== null);

        const last = points.at(-1) ?? null;

        return {
            id: player.id,
            name: player.name,
            avatar: player.avatar,
            color: colors[playerIndex % colors.length],
            points,
            lastRank: last?.rank ?? null,
        };
    });
});

const linePoints = (points: { x: number; y: number }[]): string =>
    points.map((point) => `${point.x},${point.y}`).join(' ');

const hoveredPoint = ref<HoveredPoint | null>(null);

const showPoint = (
    item: { id: number; name: string; color: string },
    point: RankPoint,
): void => {
    hoveredPoint.value = {
        ...point,
        playerId: item.id,
        name: item.name,
        color: item.color,
    };
};

const isPointHovered = (playerId: number, day: string): boolean =>
    hoveredPoint.value?.playerId === playerId && hoveredPoint.value.day === day;

const truncate = (value: string, maxLength: number): string =>
    value.length > maxLength ? `${value.slice(0, maxLength - 1)}…` : value;

const tooltipHeight = 44;

const tooltipName = computed(() =>
    hoveredPoint.value ? truncate(hoveredPoint.value.name, 30) : '',
);

const tooltipDetail = computed(() => {
    const point = hoveredPoint.value;

    return point
        ? `${formatShortDate(point.day)} · ${point.rank}º lugar · ${formatChips(point.chips)} fichas`
        : '';
});

const tooltipWidth = computed(() => {
    const nameWidth = tooltipName.value.length * 6.2 + 24;
    const detailWidth = tooltipDetail.value.length * 5.2 + 20;

    return Math.min(240, Math.max(100, nameWidth, detailWidth));
});

const tooltipPosition = computed(() => {
    const point = hoveredPoint.value;

    if (!point) {
        return { x: 0, y: 0 };
    }

    const boxWidth = tooltipWidth.value;
    const above = point.y - tooltipHeight - 8 >= padding.top;
    const y = above ? point.y - tooltipHeight - 8 : point.y + 8;
    const maxX = width - padding.right - boxWidth;
    const x = Math.min(Math.max(point.x - boxWidth / 2, padding.left), maxX);

    return { x, y };
});
</script>

<template>
    <div>
        <div
            v-if="!series.length"
            class="flex h-48 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
        >
            Sem posições para exibir. Registre fichas para ver o ranking.
        </div>

        <div v-else class="space-y-3">
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="h-auto w-full"
                role="img"
                aria-label="Posição no ranking por jogador ao longo dos dias"
            >
                <g>
                    <line
                        v-for="rank in rankTicks"
                        :key="`grid-${rank}`"
                        :x1="padding.left"
                        :x2="width - padding.right"
                        :y1="yForRank(rank)"
                        :y2="yForRank(rank)"
                        stroke="var(--border)"
                        stroke-dasharray="4 4"
                    />
                    <text
                        v-for="rank in rankTicks"
                        :key="`tick-${rank}`"
                        :x="padding.left - 8"
                        :y="yForRank(rank) + 3"
                        text-anchor="end"
                        font-size="10"
                        class="fill-muted-foreground"
                    >
                        {{ rank }}º
                    </text>
                </g>

                <text
                    v-for="(day, index) in days"
                    :key="`day-${day}`"
                    :x="xFor(index)"
                    :y="height - 8"
                    text-anchor="middle"
                    font-size="10"
                    class="fill-muted-foreground"
                >
                    {{ formatShortDate(day) }}
                </text>

                <g v-for="item in series" :key="item.id">
                    <polyline
                        :points="linePoints(item.points)"
                        fill="none"
                        :stroke="item.color"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle
                        v-for="point in item.points"
                        :key="`${item.id}-${point.day}`"
                        :cx="point.x"
                        :cy="point.y"
                        r="3"
                        :fill="item.color"
                    />
                    <circle
                        v-for="point in item.points"
                        :key="`highlight-${item.id}-${point.day}`"
                        v-show="isPointHovered(item.id, point.day)"
                        :cx="point.x"
                        :cy="point.y"
                        r="6"
                        fill="none"
                        :stroke="item.color"
                        stroke-width="2"
                        pointer-events="none"
                    />
                    <circle
                        v-for="point in item.points"
                        :key="`hit-${item.id}-${point.day}`"
                        :cx="point.x"
                        :cy="point.y"
                        r="10"
                        fill="transparent"
                        pointer-events="all"
                        class="cursor-pointer outline-none"
                        role="img"
                        tabindex="0"
                        :aria-label="`${item.name}, ${formatShortDate(point.day)}: ${point.rank}º lugar`"
                        @mouseenter="showPoint(item, point)"
                        @mouseleave="hoveredPoint = null"
                        @focus="showPoint(item, point)"
                        @blur="hoveredPoint = null"
                        @keydown.escape="hoveredPoint = null"
                    />
                </g>

                <g v-if="hoveredPoint" class="pointer-events-none">
                    <line
                        :x1="hoveredPoint.x"
                        :x2="hoveredPoint.x"
                        :y1="padding.top"
                        :y2="height - padding.bottom"
                        stroke="var(--border)"
                        stroke-dasharray="3 3"
                    />
                    <g
                        :transform="`translate(${tooltipPosition.x}, ${tooltipPosition.y})`"
                    >
                        <rect
                            :width="tooltipWidth"
                            :height="tooltipHeight"
                            rx="4"
                            fill="var(--popover)"
                            stroke="var(--border)"
                        />
                        <circle
                            cx="12"
                            cy="14"
                            r="2.5"
                            :fill="hoveredPoint.color"
                        />
                        <text
                            x="19"
                            y="17"
                            font-size="11"
                            font-weight="600"
                            class="fill-popover-foreground"
                        >
                            {{ tooltipName }}
                        </text>
                        <text
                            x="12"
                            y="34"
                            font-size="9"
                            class="fill-muted-foreground"
                        >
                            {{ tooltipDetail }}
                        </text>
                    </g>
                </g>
            </svg>

            <div class="flex flex-wrap gap-x-4 gap-y-2">
                <div
                    v-for="item in series"
                    :key="`legend-${item.id}`"
                    class="flex items-center gap-1.5 text-xs"
                >
                    <Avatar class="size-5 overflow-hidden rounded-full">
                        <AvatarImage
                            v-if="item.avatar"
                            :src="item.avatar"
                            :alt="item.name"
                        />
                        <AvatarFallback class="text-[9px]">
                            {{ getInitials(item.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span
                        class="size-2 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <span class="font-medium">{{ item.name }}</span>
                    <span class="text-muted-foreground">
                        {{ item.lastRank === null ? '—' : `${item.lastRank}º` }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
