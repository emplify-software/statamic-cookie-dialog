<template>

  <Header>
    <template #title>
      Cookie Dialog
      <sup style='font-size:0.5em'>{{ isProEdition ? 'PRO' : 'FREE' }}</sup>
    </template>
    <Button text="Save" variant="primary" :disabled="saving || localizing !== false" @click="save" />
  </Header>

  <PublishContainer
      ref="container"
      name="cookie-dialog"
      v-model="values"
      v-model:modified-fields="localizedFields"
      :blueprint="blueprint"
      :meta="meta"
      :errors="errors"
      :site="site"
      :origin-values="originValues"
      :origin-meta="originMeta"
      :sync-field-confirmation-text="__('Are you sure? The value of the default site will be used for this field.')"
  >
    <PublishTabs>
      <template v-if="localizations.length > 1" #actions>
        <PublishLocalizations
            :localizations="localizations"
            :localizing="localizing"
            @selected="localizationSelected"
        />
      </template>
    </PublishTabs>
  </PublishContainer>

  <ConfirmationModal
      :open="pendingLocalization !== null"
      :title="__('Unsaved Changes')"
      :body-text="__('Are you sure? Unsaved changes will be lost.')"
      :button-text="__('Continue')"
      :danger="true"
      @confirm="confirmSwitchLocalization"
      @cancel="pendingLocalization = null"
  />

</template>

<script setup>
import { Header, PublishContainer, PublishTabs, PublishLocalizations, ConfirmationModal, Button } from '@statamic/cms/ui';
import { Pipeline, PipelineStopped, BeforeSaveHooks, Request, AfterSaveHooks } from '@statamic/cms/save-pipeline';
import { dirty } from '@statamic/cms/api';
import { ref, useTemplateRef, onMounted, onUnmounted, getCurrentInstance, nextTick } from 'vue';

const props = defineProps({
    blueprint: Object,
    initialValues: Object,
    initialMeta: Object,
    site: String,
    originValues: Object,
    originMeta: Object,
    localizedFields: Array,
    localizations: Array,
    saveUrl: String,
    isProEdition: Boolean,
});

const { $axios } = getCurrentInstance().proxy;

const blueprint = ref(props.blueprint);
const values = ref(props.initialValues);
const meta = ref(props.initialMeta);
const site = ref(props.site);
const originValues = ref(props.originValues);
const originMeta = ref(props.originMeta);
const localizedFields = ref(props.localizedFields ?? []);
const localizations = ref(props.localizations ?? []);
const saveUrl = ref(props.saveUrl);

const saving = ref(false);
const errors = ref({});
const container = useTemplateRef('container');

const localizing = ref(false);
const pendingLocalization = ref(null);

let quickSaveKeyBinding, saveKeyBinding;
onMounted(() => {
    quickSaveKeyBinding = Statamic.$keys.bindGlobal(['mod+s'], (e) => {
      e.preventDefault();
      save();
    });
    saveKeyBinding = Statamic.$keys.bindGlobal(['mod+return'], (e) => {
      e.preventDefault();
      save();
    });
});
onUnmounted(() => {
  quickSaveKeyBinding.destroy();
  saveKeyBinding.destroy();
});

function save() {
    new Pipeline()
        .provide({ container, errors, saving })
        .through([
            new BeforeSaveHooks('cookie-dialog', { site: site.value }),
            new Request(saveUrl.value, 'PATCH', { _localized: localizedFields.value }),
            new AfterSaveHooks('cookie-dialog', { site: site.value }),
        ])
        .then((response) => {
            Statamic.$toast.success(__('Saved'));
        })
        .catch((e) => {
            if (!(e instanceof PipelineStopped)) {
                Statamic.$toast.error(__('Something went wrong'));
                console.error(e);
            }
        });
}

function localizationSelected(localization) {
    if (localization.active) return;

    if (dirty.names().includes('cookie-dialog')) {
        pendingLocalization.value = localization;
        return;
    }

    switchToLocalization(localization);
}

function confirmSwitchLocalization() {
    switchToLocalization(pendingLocalization.value);
    pendingLocalization.value = null;
}

function switchToLocalization(localization) {
    localizing.value = localization.handle;
    window.history.replaceState({}, '', localization.url);

    $axios.get(localization.url).then(({ data }) => {
        blueprint.value = data.blueprint;
        values.value = data.initialValues;
        meta.value = data.initialMeta;
        originValues.value = data.originValues;
        originMeta.value = data.originMeta;
        localizedFields.value = data.localizedFields;
        localizations.value = data.localizations;
        saveUrl.value = data.saveUrl;
        site.value = data.site;
        errors.value = {};
        nextTick(() => container.value.clearDirtyState());
    }).catch((e) => {
        Statamic.$toast.error(__('Something went wrong'));
        console.error(e);
    }).finally(() => {
        localizing.value = false;
    });
}
</script>
