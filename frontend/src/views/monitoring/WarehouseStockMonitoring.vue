<script setup>
import Inventory from "../inventory/Inventory.vue";
</script>

<template>
    <div class="space-y-6">
        <Inventory 
            pageMode="warehouse" 
            title="Monitoring Stok Gudang" 
            subtitle="Pantau ketersediaan stok barang pada gudang yang Anda kelola." 
            :hideActions="true" 
        />
    </div>
</template>

