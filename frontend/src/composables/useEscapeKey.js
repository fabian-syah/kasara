import { onMounted, onUnmounted } from 'vue';

/**
 * Composable to handle Escape key press.
 * @param {Function} onEscape Callback to run when Escape is pressed.
 * @param {import('vue').Ref<boolean>} [isActive] Optional ref to determine if the listener should be active.
 */
export function useEscapeKey(onEscape, isActive = null) {
    const handleKeydown = (e) => {
        if (e.key === 'Escape') {
            if (isActive === null || isActive.value) {
                onEscape();
            }
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });
}
