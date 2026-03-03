<script setup>
import Inventory from "../inventory/Inventory.vue";
</script>

<template>
    <div class="space-y-6">
        <Inventory 
            pageMode="online_shop" 
            title="Monitoring Stok Online" 
            subtitle="Pantau ketersediaan stok barang pada toko online yang Anda kelola." 
            :hideActions="true" 
        />
    </div>
</template>

