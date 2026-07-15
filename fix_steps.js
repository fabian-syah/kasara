const fs = require('fs');
let code = fs.readFileSync('frontend/src/views/audit/AuditInputTransfer.vue', 'utf8');

// 1. Initial state
code = code.replace(/const currentStep = ref\(2\); \/\/ Skip step 1/, 'const currentStep = ref(1);');

// 2. Breadcrumbs
code = code.replace(/v-for="step in \[1, 2, 3, 4\]"/, 'v-for="step in [1, 2, 3]"');
code = code.replace(/v-if="step < 4" class="w-16 h-1 mx-2 rounded-full"/, 'v-if="step < 3" class="w-16 h-1 mx-2 rounded-full"');

// 3. Remove old Step 1 completely
code = code.replace(/<div v-if="false"[^]*?<!-- Modal Edit Account -->[^]*?<\/div>\s*<\/div>/, '');

// 4. Shift steps
code = code.replace(/v-if="currentStep === 2"/, 'v-if="currentStep === 1"');
code = code.replace(/v-if="currentStep === 3"/, 'v-if="currentStep === 2"');
code = code.replace(/v-if="currentStep === 4"/, 'v-if="currentStep === 3"');
code = code.replace(/<button v-if="currentStep < 4"/, '<button v-if="currentStep < 3"');
code = code.replace(/<button v-if="currentStep === 4"/, '<button v-if="currentStep === 3"');
code = code.replace(/<button v-if="currentStep > 1"/, '<button v-if="currentStep > 1"');

// 5. canNext
code = code.replace(
`const canNext = computed(() => {
    if (currentStep.value === 1) return !!placementId.value;
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});`,
`const canNext = computed(() => {
    if (currentStep.value === 1) return !!itemType.value;
    if (currentStep.value === 2) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});`
);
// In case the old code was already modified without the first if:
code = code.replace(
`const canNext = computed(() => {
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});`,
`const canNext = computed(() => {
    if (currentStep.value === 1) return !!itemType.value;
    if (currentStep.value === 2) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});`
);

// 6. nextStep
code = code.replace(
`function nextStep() {
    if (canNext.value) {
        if (currentStep.value === 2 && isDistributorRole.value) {
            selectedDistributor.value = authStore.user?.distributor_id || "";
            currentStep.value = 4;
            return;
        }
        currentStep.value++;
    }
}`,
`function nextStep() {
    if (canNext.value) {
        if (currentStep.value === 1 && isDistributorRole.value) {
            selectedDistributor.value = authStore.user?.distributor_id || "";
            currentStep.value = 3;
            return;
        }
        currentStep.value++;
    }
}`
);

// 7. prevStep
code = code.replace(
`function prevStep() {
    if (currentStep.value > 1) {
        if (currentStep.value === 4 && isDistributorRole.value) {
            currentStep.value = 2;
            return;
        }
        currentStep.value--;
    }
}`,
`function prevStep() {
    if (currentStep.value > 1) {
        if (currentStep.value === 3 && isDistributorRole.value) {
            currentStep.value = 1;
            return;
        }
        currentStep.value--;
    }
}`
);

// 8. Remove the placement text from header in Step 3
code = code.replace(
`<div class="px-2">Akun: <span class="text-text-primary">{{ placementName }}</span></div>`,
``
);
// Fix the grid columns from 3 to 2 for the header
code = code.replace(
`grid-cols-3`,
`grid-cols-2`
);

fs.writeFileSync('frontend/src/views/audit/AuditInputTransfer.vue', code);
