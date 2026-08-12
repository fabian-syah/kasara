<script setup>
import { ref, onMounted, nextTick, watch } from "vue";
import { Lock, AlertCircle } from "lucide-vue-next";
import { useAuthStore } from "../../store/auth";

const authStore = useAuthStore();

const props = defineProps({
    show: Boolean,
    title: { type: String, default: "Verifikasi PIN Keamanan" },
    description: { type: String, default: "Masukkan PIN keamanan akun Anda untuk melanjutkan." },
    user: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: "" }
});

const emit = defineEmits(["close", "success", "verified", "error"]);

const password = ref("");
const inputRef = ref(null);
const localLoading = ref(false);

function handleSubmit() {
    if (!password.value) return;

    emit("success", password.value);
    emit("verified", password.value);
    resetPassword();
}

watch(() => props.error, (newVal) => {
    if (newVal) {
        resetPassword();
    }
});

function resetPassword() {
    password.value = "";
    nextTick(() => {
        if (inputRef.value) inputRef.value.focus();
    });
}

function close() {
    resetPassword();
    emit("close");
}

onMounted(() => {
    if (props.show) {
        nextTick(() => {
            if (inputRef.value) inputRef.value.focus();
        });
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" v-bind="$attrs">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="close"></div>

            <!-- Modal Content -->
            <div
                class="relative w-full max-w-sm bg-white dark:bg-surface-900 border border-gray-200 dark:border-white/10 rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 to-emerald-800"></div>

                <div class="p-8 text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 mb-6 font-black font-mono">
                        <Lock :size="32" stroke-width="2.5" />
                    </div>

                    <h3 class="text-2xl font-black text-text-primary mb-2">{{ title }}</h3>
                    <p v-if="user" class="text-sm font-bold text-emerald-500 mb-2">
                        Akun: {{ user.name || user.full_name }}
                    </p>
                    <p class="text-text-secondary text-sm mb-6 leading-relaxed px-4">
                        {{ description }}
                    </p>

                    <form @submit.prevent="handleSubmit" class="mb-6 px-4">
                        <input
                            ref="inputRef"
                            v-model="password"
                            type="password"
                            placeholder="PIN Keamanan..."
                            autocomplete="new-password"
                            class="w-full h-14 bg-surface-50 dark:bg-white/5 border border-surface-200 dark:border-white/10 rounded-2xl px-4 text-center text-xl font-bold text-text-primary focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all placeholder:text-text-secondary/50"
                        />
                        <button type="submit" class="hidden">Submit</button>
                    </form>

                    <div v-if="error"
                        class="flex items-center justify-center gap-2 text-rose-500 text-sm font-bold mb-6 animate-pulse">
                        <AlertCircle :size="16" />
                        {{ error }}
                    </div>

                    <div v-if="loading || localLoading" class="flex items-center justify-center gap-3 py-4">
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce"></div>
                    </div>

                    <button @click="close" type="button"
                        class="text-text-secondary/60 hover:text-text-primary text-sm font-bold transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
