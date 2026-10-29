<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, ChartLine, Trophy } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

const features = [
    {
        icon: CalendarDays,
        title: 'Rodadas organizadas',
        description:
            'Abra uma rodada, defina a data e acompanhe as fichas em jogo.',
    },
    {
        icon: Trophy,
        title: 'Ranking automático',
        description:
            'Cada vitória em rodadas atualiza a tabela e mostra a classificação dos jogadores.',
    },
    {
        icon: ChartLine,
        title: 'Histórico do dia',
        description:
            'Snapshots guardam a evolução das fichas para você conferir tudo depois.',
    },
];

const heroGlow =
    'radial-gradient(ellipse at center, color-mix(in srgb, var(--primary) 16%, transparent) 0%, color-mix(in srgb, var(--primary) 6%, transparent) 40%, transparent 70%)';
</script>

<template>
    <Head title="Início" />

    <div
        class="relative flex min-h-svh flex-col overflow-hidden bg-background text-foreground"
    >
        <header class="relative">
            <nav class="mx-auto flex w-full max-w-5xl items-center px-6 py-4">
                <Link :href="home()" class="flex items-center gap-3">
                    <AppLogoIcon class="size-11" />
                    <span class="text-base font-semibold tracking-tight">
                        {{ $page.props.name }}
                    </span>
                </Link>
            </nav>
        </header>

        <main
            class="relative isolate mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-6 pt-4 pb-10 text-center sm:pt-6 sm:pb-14"
        >
            <div class="relative flex w-full flex-col items-center">
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute top-1/2 left-1/2 -z-10 h-[45vh] w-[60vw] max-w-[64rem] -translate-x-1/2 -translate-y-1/2"
                    :style="{ background: heroGlow }"
                />

                <h1
                    class="max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Suas fichas, rodada por rodada.
                </h1>
                <p
                    class="mt-5 max-w-xl text-base text-pretty text-muted-foreground sm:text-lg"
                >
                    Monte rodadas, registre os stacks do dia e acompanhe o
                    ranking — nada de planilhas, ideal para viciados e
                    preguiçosos.
                </p>

                <div
                    class="mt-8 flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row"
                >
                    <template v-if="$page.props.auth.user">
                        <Button as-child size="lg" class="w-full sm:w-auto">
                            <Link :href="dashboard()">
                                Comece a monitorar
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button as-child size="lg" class="w-full sm:w-auto">
                            <Link :href="register()">
                                Sentar na mesa
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            size="lg"
                            class="w-full sm:w-auto"
                        >
                            <Link :href="login()">Já tenho conta</Link>
                        </Button>
                    </template>
                </div>
            </div>

            <section class="mt-12 grid w-full gap-4 text-left sm:grid-cols-3">
                <article
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border bg-card p-5 shadow-xs transition-colors hover:border-primary/30"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <component :is="feature.icon" class="size-4.5" />
                    </div>
                    <h2 class="mt-4 text-sm font-semibold">
                        {{ feature.title }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ feature.description }}
                    </p>
                </article>
            </section>
        </main>

        <footer class="relative border-t border-border/60 py-5">
            <p class="text-center text-xs text-muted-foreground">
                {{ $page.props.name }} — um app muito engraçado.
            </p>
        </footer>
    </div>
</template>
