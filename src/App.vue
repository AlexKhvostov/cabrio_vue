<script setup lang="ts"></script>

<template>
  <div class="min-h-screen sunset-bg">
    <router-view />
  </div>
</template>

<style>
.sunset-bg {
  background:
    radial-gradient(120% 60% at 15% -10%, rgba(255, 138, 92, 0.18), transparent 60%),
    radial-gradient(100% 50% at 100% 0%, rgba(94, 200, 216, 0.14), transparent 60%),
    #161a2e;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
