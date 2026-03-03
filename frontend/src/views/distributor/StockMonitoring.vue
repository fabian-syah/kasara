<script setup>
import Inventory from "../inventory/Inventory.vue";
</script>

<template>
    <div class="space-y-6">
        <Inventory 
            pageMode="distributor" 
            title="Monitoring Stok Distributor" 
            subtitle="Pantau ketersediaan stok barang yang Anda suplai ke berbagai lokasi cabang dan toko online." 
            :hideActions="true" 
        />
    </div>
</template>

