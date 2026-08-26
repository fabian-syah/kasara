<script setup>
import { ref, onMounted, nextTick, watch, computed } from "vue";
import { Lock, AlertCircle, Eye, EyeOff } from "lucide-vue-next";
import { useAuthStore } from "../../store/auth";

const authStore = useAuthStore();

const props = defineProps({
    show: Boolean,
    mode: { type: String, default: "password" }, // 'pin' or 'password'
    title: { type: String, default: "" },
    description: { type: String, default: "" },
    user: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: "" }
});

const emit = defineEmits(["close", "success", "verified", "error"]);

const inputVal = ref("");
const inputRef = ref(null);
const localLoading = ref(false);
const showPassword = ref(false);

const modalTitle = computed(() => {
    if (props.title) return props.title;
    return props.mode === 'pin' ? 'Verifikasi PIN' : 'Verifikasi Password';
});

const modalDescription = computed(() => {
    if (props.description) return props.description;
    return props.mode === 'pin' 
        ? 'Masukkan PIN Keamanan Anda untuk melanjutkan.' 
        : 'Masukkan Password Login untuk melanjutkan.';
});

const inputPlaceholder = computed(() => {
    return props.mode === 'pin' ? 'PIN Keamanan...' : 'Password Login...';
});

let isClickLocked = false;
function handleSubmit() {
    if (isClickLocked) return;
    if (!inputVal.value) return;

    isClickLocked = true;
    setTimeout(() => { isClickLocked = false; }, 1000);

    emit("success", inputVal.value);
    emit("verified", inputVal.value);
    resetInput();
}

watch(() => props.error, (newVal) => {
    if (newVal) {
        resetInput();
    }
});

function resetInput() {
    inputVal.value = "";
    showPassword.value = false;
    nextTick(() => {
        if (inputRef.value) inputRef.value.focus();
    });
}

function close() {
    resetInput();
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

                <div class="p-6 sm:p-8 text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-6 font-black font-mono"
                        :class="mode === 'alert' ? 'bg-rose-500/10 text-rose-500' : 'bg-emerald-500/10 text-emerald-500'">
                        <Lock v-if="mode !== 'alert'" :size="32" stroke-width="2.5" />
                        <AlertCircle v-else :size="32" stroke-width="2.5" />
                    </div>

                    <h3 class="text-2xl font-black text-text-primary mb-2">{{ modalTitle }}</h3>
                    <p v-if="user" class="text-sm font-bold text-emerald-500 mb-2" :class="mode === 'alert' ? 'text-rose-500' : 'text-emerald-500'">
                        Akun: {{ user.name || user.full_name }}
                    </p>
                    <p class="text-text-secondary text-sm mb-6 leading-relaxed px-4" :class="mode === 'alert' ? 'text-rose-400 font-bold' : ''">
                        {{ modalDescription }}
                    </p>

                    <form v-if="mode !== 'alert'" @submit.prevent="handleSubmit" class="mb-6 px-4 flex flex-col gap-4">
                        <div class="relative">
                            <input
                                ref="inputRef"
                                v-model="inputVal"
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="inputPlaceholder"
                                autocomplete="new-password"
                                :maxlength="mode === 'pin' ? 4 : 255"
                                class="w-full h-14 bg-surface-50 dark:bg-white/5 border border-surface-200 dark:border-white/10 rounded-2xl px-4 pr-12 text-center text-xl font-bold text-text-primary focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all placeholder:text-text-secondary/50"
                            />
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                <Eye v-if="!showPassword" :size="20" />
                                <EyeOff v-else :size="20" />
                            </button>
                        </div>
                        <button type="submit" :disabled="!inputVal || loading || localLoading"
                            class="w-full py-3.5 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            Lanjutkan
                        </button>
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

                    <button v-if="mode !== 'alert'" @click="close" type="button"
                        class="text-text-secondary/60 hover:text-text-primary text-sm font-bold transition-colors">
                        Batal
                    </button>
                    
                    <button v-if="mode === 'alert'" @click="close" type="button"
                        class="w-full py-3.5 bg-rose-500 hover:bg-rose-600 text-white font-black rounded-xl transition-colors flex items-center justify-center gap-2 mt-2">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
