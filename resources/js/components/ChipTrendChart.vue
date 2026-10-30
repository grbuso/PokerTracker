<script setup lang="ts">
import { computed, ref } from 'vue';

type ChartPlayer = {
    id: number;
    name: string;
};

type ChartSnapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

type ChartPoint = {
    x: number;
    y: number;
    day: string;
    chips: number;
};

type HoveredPoint = ChartPoint & {
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
];

const chipFormatter = new Intl.NumberFormat('pt-BR');

const formatChips = (chips: number): string => chipFormatter.format(chips);

const formatDay = (day: string): string => {
    const [, month, date] = day.split('-');

    return month && date ? `${date}/${month}` : day;
};

const days = computed(() =>
    [
        ...new Set(props.snapshots.map((snapshot) => snapshot.recorded_on)),
    ].sort(),
);

const chartPlayers = computed(() =>
    props.players.filter((player) =>
        props.snapshots.some((snapshot) => snapshot.user_id === player.id),
    ),
);

const niceStep = (value: number): number => {
    if (value <= 1) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const normalized = value / magnitude;
    const step =
        normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

    return step * magnitude;
};

const maxChips = computed(() => {
    const highest = Math.max(
        1,
        ...props.snapshots.map((snapshot) => snapshot.chips),
    );
    const axisMax = Math.max(highest, 4);
    const step = niceStep(axisMax / 4);

    return Math.max(step, Math.ceil(axisMax / step) * step);
});

const ticks = computed(() =>
    Array.from({ length: 5 }, (_, index) => (maxChips.value / 4) * index),
);

const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const xFor = (index: number): number => {
    if (days.value.length === 1) {
        return padding.left + plotWidth / 2;
    }

    return padding.left + (index / (days.value.length - 1)) * plotWidth;
};

const yFor = (chips: number): number =>
    padding.top + (1 - chips / maxChips.value) * plotHeight;

const series = computed(() => {
    const dayIndexes = new Map(days.value.map((day, index) => [day, index]));

    return chartPlayers.value.map((player, playerIndex) => {
        const points = props.snapshots
            .filter((snapshot) => snapshot.user_id === player.id)
            .sort((first, second) =>
                first.recorded_on.localeCompare(second.recorded_on),
            )
            .map((snapshot) => {
                const dayIndex = dayIndexes.get(snapshot.recorded_on) ?? 0;

                return {
                    x: xFor(dayIndex),
                    y: yFor(snapshot.chips),
                    day: snapshot.recorded_on,
                    chips: snapshot.chips,
                };
            });

        return {
            id: player.id,
            name: player.name,
            color: colors[playerIndex % colors.length],
            points,
            last: points.at(-1)?.chips ?? null,
        };
    });
});

const linePoints = (points: { x: number; y: number }[]): string =>
    points.map((point) => `${point.x},${point.y}`).join(' ');

const hoveredPoint = ref<HoveredPoint | null>(null);

const showPoint = (
    item: { id: number; name: string; color: string },
    point: ChartPoint,
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

const tooltipHeight = 32;

const tooltipName = computed(() =>
    hoveredPoint.value ? truncate(hoveredPoint.value.name, 24) : '',
);

const tooltipDetail = computed(() => {
    const point = hoveredPoint.value;

    return point
        ? `${formatDay(point.day)} · ${formatChips(point.chips)} fichas`
        : '';
});

const tooltipWidth = computed(() => {
    const longest = Math.max(
        tooltipName.value.length,
        tooltipDetail.value.length,
    );

    return Math.min(160, Math.max(80, longest * 4.6 + 20));
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
            Sem snapshots ainda. Salve o primeiro dia e as linhas aparecem.
        </div>

        <div v-else class="space-y-3">
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="h-auto w-full"
                role="img"
                aria-label="Evolução das fichas por jogador"
            >
                <g>
                    <line
                        v-for="tick in ticks"
                        :key="`grid-${tick}`"
                        :x1="padding.left"
                        :x2="width - padding.right"
                        :y1="yFor(tick)"
                        :y2="yFor(tick)"
                        stroke="var(--border)"
                        stroke-dasharray="4 4"
                    />
                    <text
                        v-for="tick in ticks"
                        :key="`tick-${tick}`"
                        :x="padding.left - 8"
                        :y="yFor(tick) + 3"
                        text-anchor="end"
                        font-size="10"
                        class="fill-muted-foreground"
                    >
                        {{ formatChips(tick) }}
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
                    {{ formatDay(day) }}
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
                        :aria-label="`${item.name}, ${formatDay(point.day)}: ${formatChips(point.chips)} fichas`"
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
                            cx="9"
                            cy="10.5"
                            r="2"
                            :fill="hoveredPoint.color"
                        />
                        <text
                            x="15"
                            y="13"
                            font-size="8"
                            font-weight="600"
                            class="fill-popover-foreground"
                        >
                            {{ tooltipName }}
                        </text>
                        <text
                            x="9"
                            y="25"
                            font-size="7"
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
                    <span
                        class="size-2 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <span class="font-medium">{{ item.name }}</span>
                    <span class="text-muted-foreground">
                        {{ item.last === null ? '—' : formatChips(item.last) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
