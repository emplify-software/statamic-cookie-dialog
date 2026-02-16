<template>

  <Header>
    <template #title>
      Cookie Dialog
      <sup style='font-size:0.5em'>{{ isProEdition ? 'PRO' : 'FREE' }}</sup>
    </template>
    <Button text="Save" variant="primary" :disabled="saving" @click="save" />
  </Header>

  <PublishContainer
      ref="container"
      v-model="values"
      :blueprint="blueprint"
      :meta="meta"
      :errors="errors"
  />

</template>

<script setup>
import { Header, PublishContainer, Button } from '@statamic/cms/ui';
import { Pipeline, PipelineStopped, BeforeSaveHooks, Request, AfterSaveHooks } from '@statamic/cms/save-pipeline';
import { ref, useTemplateRef, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    blueprint: Object,
    initialValues: Object,
    initialMeta: Object,
    isProEdition: Boolean,
});

const values = ref(props.initialValues);
const meta = ref(props.initialMeta);

const saving = ref(false);
const errors = ref({});
const container = useTemplateRef('container');

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
            new BeforeSaveHooks('cookie-dialog'),
            new Request('/cp/cookie-dialog', 'PATCH'),
            new AfterSaveHooks('cookie-dialog'),
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
</script>