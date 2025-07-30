<template>
  <div id="app" :dir="$i18n.locale === 'fa' ? 'rtl' : 'ltr'" :class="$i18n.locale === 'fa' ? 'font-vazir' : 'font-inter'">
    <router-view />
  </div>
</template>

<script>
export default {
  name: 'App'
}
</script>

<style>
/* Global styles are handled in app.css */
</style>