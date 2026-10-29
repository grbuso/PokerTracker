<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Trash2, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import PlayerController from '@/actions/App/Http/Controllers/Admin/PlayerController';
import Heading from '@/components/Heading.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Player = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    rounds_count: number;
    is_current_user: boolean;
};

defineProps<{ players: Player[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Jogadores',
                href: PlayerController.index.url(),
            },
        ],
    },
});

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submitCreate = () => {
    createForm.post(PlayerController.store.url(), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const editing = ref<Player | null>(null);
const editOpen = ref(false);

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const openEdit = (player: Player) => {
    editing.value = player;
    editForm.name = player.name;
    editForm.email = player.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.clearErrors();
    editOpen.value = true;
};

const submitEdit = () => {
    if (!editing.value) {
        return;
    }

    editForm.put(PlayerController.update.url(editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editing.value = null;
            editForm.reset();
        },
    });
};

const deleting = ref<Player | null>(null);
const deleteOpen = ref(false);

const requestDelete = (player: Player) => {
    deleting.value = player;
    deleteOpen.value = true;
};

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }

    router.delete(PlayerController.destroy.url(deleting.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteOpen.value = false;
            deleting.value = null;
        },
    });
};

const deleteBlockedReason = (player: Player): string | null => {
    if (player.is_current_user || player.is_admin) {
        return 'A conta do administrador não pode ser removida.';
    }

    if (player.rounds_count > 0) {
        return 'Jogador com histórico de rodadas não pode ser removido.';
    }

    return null;
};
</script>

<template>
    <Head title="Jogadores" />

    <h1 class="sr-only">Jogadores</h1>

    <div class="relative isolate flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Jogadores"
            description="Gerencie quem senta à mesa. Apenas o administrador pode criar, editar e remover jogadores."
        />

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Todos os jogadores</CardTitle>
                    <CardDescription>
                        {{ players.length }}
                        {{ players.length === 1 ? 'jogador' : 'jogadores' }} no
                        banco.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="players.length"
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
                                    <th class="px-3 py-2 font-medium">
                                        E-mail
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Rodadas
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="player in players"
                                    :key="player.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex items-center gap-2"
                                        >
                                            {{ player.name }}
                                            <Badge
                                                v-if="player.is_admin"
                                                variant="outline"
                                            >
                                                Admin
                                            </Badge>
                                            <Badge
                                                v-else-if="
                                                    player.is_current_user
                                                "
                                                variant="outline"
                                            >
                                                Você
                                            </Badge>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-muted-foreground">
                                        {{ player.email }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ player.rounds_count }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`Editar ${player.name}`"
                                                @click="openEdit(player)"
                                            >
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`Remover ${player.name}`"
                                                :disabled="
                                                    deleteBlockedReason(
                                                        player,
                                                    ) !== null
                                                "
                                                :title="
                                                    deleteBlockedReason(
                                                        player,
                                                    ) ?? undefined
                                                "
                                                @click="requestDelete(player)"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum jogador cadastrado ainda.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Novo jogador</CardTitle>
                    <CardDescription>
                        Crie uma conta para alguém sentar à mesa.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div class="grid gap-2">
                            <Label for="name">Nome</Label>
                            <Input
                                id="name"
                                v-model="createForm.name"
                                required
                                autocomplete="name"
                                placeholder="Nome do jogador"
                            />
                            <InputError :message="createForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">E-mail</Label>
                            <Input
                                id="email"
                                v-model="createForm.email"
                                type="email"
                                required
                                autocomplete="off"
                                placeholder="email@exemplo.com"
                            />
                            <InputError :message="createForm.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">Senha</Label>
                            <Input
                                id="password"
                                v-model="createForm.password"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Senha inicial"
                            />
                            <InputError :message="createForm.errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">
                                Confirmar senha
                            </Label>
                            <Input
                                id="password_confirmation"
                                v-model="createForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Repita a senha"
                            />
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="createForm.processing"
                            >
                                <UserPlus class="size-4" />
                                Criar jogador
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="editOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar {{ editing?.name }}</DialogTitle>
                    <DialogDescription>
                        Deixe a senha em branco para manter a atual.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <Label for="edit-name">Nome</Label>
                        <Input
                            id="edit-name"
                            v-model="editForm.name"
                            required
                            autocomplete="name"
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-email">E-mail</Label>
                        <Input
                            id="edit-email"
                            v-model="editForm.email"
                            type="email"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="editForm.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-password">Nova senha</Label>
                        <Input
                            id="edit-password"
                            v-model="editForm.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Opcional"
                        />
                        <InputError :message="editForm.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-password-confirmation">
                            Confirmar nova senha
                        </Label>
                        <Input
                            id="edit-password-confirmation"
                            v-model="editForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Opcional"
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose as-child>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="editForm.processing">
                            Salvar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remover {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription>
                        A conta será apagada permanentemente. Jogadores com
                        histórico de rodadas não podem ser removidos.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="confirmDelete">
                        Remover
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
