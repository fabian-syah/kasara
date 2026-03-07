<script setup>
import { ref, onMounted, nextTick } from "vue";
import { Lock, X, AlertCircle, CheckCircle } from "lucide-vue-next";
import api from "../../api/axios";

const props = defineProps({
    show: Boolean,
    title: { type: String, default: "Verifikasi PIN Transaksi" },
    description: { type: String, default: "Masukkan 4 digit PIN transaksi Anda untuk melanjutkan." },
    mode: { type: String, default: "verify" }, // 'verify', 'set', 'setup_initial'
});

const emit = defineEmits(["close", "success", "error"]);

const pin = ref(["", "", "", ""]);
const inputs = ref([]);
const error = ref("");
const loading = ref(false);

function handleInput(index, event) {
    const val = event.target.value;
    if (val && !/^\d+$/.test(val)) {
        pin.value[index] = "";
        return;
    }

    if (val.length > 1) {
        pin.value[index] = val.slice(-1);
    }

    if (val && index < 3) {
        inputs.value[index + 1].focus();
    }

    if (pin.value.every(v => v !== "")) {
        handleSubmit();
    }
}

function handleKeydown(index, event) {
    if (event.key === "Backspace" && !pin.value[index] && index > 0) {
        inputs.value[index - 1].focus();
    }
}

async function handleSubmit() {
    const pinStr = pin.value.join("");
    if (pinStr.length < 4) return;

    loading.value = true;
    error.value = "";

    try {
        if (props.mode === "verify") {
            await api.post("/pin/verify", { pin: pinStr });
            emit("success", pinStr);
        } else if (props.mode === "set" || props.mode === "setup_initial") {
            await api.post("/pin/set", { pin: pinStr });
            emit("success", pinStr);
        }
        resetPin();
    } catch (err) {
        error.value = err.response?.data?.message || "PIN salah atau terjadi kesalahan.";
        resetPin();
        emit("error", error.value);
    } finally {
        loading.value = false;
    }
}

function resetPin() {
    pin.value = ["", "", "", ""];
    nextTick(() => {
        if (inputs.value[0]) inputs.value[0].focus();
    });
}

function close() {
    resetPin();
    emit("close");
}

onMounted(() => {
    if (props.show) {
        nextTick(() => {
            if (inputs.value[0]) inputs.value[0].focus();
        });
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="close"></div>

            <!-- Modal Content -->
            <div
                class="relative w-full max-w-sm bg-surface-900 border border-white/10 rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-500 to-primary-800"></div>

                <div class="p-8 text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-500/10 text-primary-500 mb-6">
                        <Lock :size="32" stroke-width="2.5" />
                    </div>

                    <h3 class="text-2xl font-black text-white mb-2">{{ title }}</h3>
                    <p class="text-white/60 text-sm mb-8 leading-relaxed px-4">
                        {{ description }}
                    </p>

                    <div class="flex justify-center gap-3 mb-8">
                        <input v-for="(digit, idx) in 4" :key="idx" :ref="el => inputs[idx] = el" v-model="pin[idx]"
                            type="password" inputmode="numeric" maxlength="1"
                            class="w-14 h-16 bg-white/5 border border-white/10 rounded-2xl text-center text-3xl font-black text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all"
                            @input="handleInput(idx, $event)" @keydown="handleKeydown(idx, $event)" />
                    </div>

                    <div v-if="error"
                        class="flex items-center justify-center gap-2 text-rose-500 text-sm font-bold mb-6 animate-pulse">
                        <AlertCircle :size="16" />
                        {{ error }}
                    </div>

                    <div v-if="loading" class="flex items-center justify-center gap-3 py-4">
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce"></div>
                    </div>

                    <button @click="close" class="text-white/40 hover:text-white text-sm font-bold transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Firefox */
input[type=number] {
    -moz-appearance: textfield;
}
</style>
