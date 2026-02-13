import Settings from './components/Settings.vue';
Statamic.booting(() => {
    Statamic.$inertia.register('statamic-cookie-dialog::settings', Settings);
});