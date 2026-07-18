<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { useDragAndDrop } from '@formkit/drag-and-drop/vue'

definePage({ meta: { requiresAdmin: true } })

const route    = useRoute()
const courseId = route.params.id

const API_STORAGE = (import.meta.env.VITE_API_URL || 'http://localhost:8000').replace(/\/$/, '')

// ─── Data ─────────────────────────────────────────────────────────────────────
const isLoading  = ref(true)
const isSavingOrder = ref(false)

// ── Module drag-and-drop list ──────────────────────────────────────────────
const [modulesParent, modules] = useDragAndDrop<any>([], {
  dragHandle: '.module-drag-handle',
  onSort: () => { saveModulesOrder() },
})

// Collapsed state per module id
const collapsedMap = ref<Record<number, boolean>>({})
const toggleCollapse = (id: number) => {
  collapsedMap.value[id] = !collapsedMap.value[id]
  if (!collapsedMap.value[id] && !attachmentsMap.value[id]) {
    loadAttachments(id)
  }
}

// Per-module lesson drag parents (imperative approach)
const lessonParents = ref<Record<number, HTMLElement | null>>({})

const fetchCurriculum = async () => {
  try {
    const res = await api.get(`/api/dashboard/courses/${courseId}?include=modules.lessons,modules.quizzes`)
    const raw: any[] = res.data.course?.modules || []
    raw.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
    raw.forEach(mod => {
      const lessons = (mod.lessons || []).map((l: any) => ({ ...l, item_type: 'lesson' }))
      const quizzes = (mod.quizzes  || []).map((q: any) => ({ ...q, item_type: 'quiz' }))
      mod.items = [...lessons, ...quizzes].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
      // Default all panels open
      if (collapsedMap.value[mod.id] === undefined) collapsedMap.value[mod.id] = false
    })
    modules.value = raw
  } catch (e) {
    console.error('Failed to load curriculum', e)
  } finally {
    isLoading.value = false
  }
}

fetchCurriculum()

// ── Save module order after drag ──────────────────────────────────────────
const saveModulesOrder = async () => {
  isSavingOrder.value = true
  try {
    await api.put('/api/dashboard/modules/reorder', {
      items: modules.value.map((m, i) => ({ id: m.id, order: i })),
    })
  } catch (e) { console.error('Failed to save module order', e) }
  finally { isSavingOrder.value = false }
}

// ── Save lesson order after drag ──────────────────────────────────────────
const saveLessonsOrder = async (moduleId: number, items: any[]) => {
  try {
    await api.put('/api/dashboard/lessons/reorder', {
      items: items
        .filter(i => i.item_type === 'lesson')
        .map((l, idx) => ({ id: l.id, order: idx })),
    })
  } catch (e) { console.error('Failed to save lesson order', e) }
}

// Register lesson drag for a module when its list mounts
// We use the imperative dragAndDrop API because modules are dynamic
import { dragAndDrop } from '@formkit/drag-and-drop/vue'

const initLessonDrag = (el: HTMLElement | null, mod: any) => {
  if (!el || lessonParents.value[mod.id] === el) return
  lessonParents.value[mod.id] = el
  dragAndDrop({
    parent: el,
    getValues: () => mod.items,
    setValues: (vals: any[]) => {
      mod.items = vals
      saveLessonsOrder(mod.id, vals)
    },
    dragHandle: '.lesson-drag-handle',
  })
}

// ─── Add Module Dialog ────────────────────────────────────────────────────────
const isAddModuleVisible = ref(false)
const isAddingModule     = ref(false)
const addModuleTitle     = ref('')

const submitAddModule = async () => {
  if (!addModuleTitle.value.trim()) return
  isAddingModule.value = true
  try {
    await api.post('/api/dashboard/modules', { course_id: courseId, title: addModuleTitle.value })
    addModuleTitle.value = ''
    isAddModuleVisible.value = false
    await fetchCurriculum()
  } catch (e) { console.error(e) }
  finally { isAddingModule.value = false }
}

// ─── Edit Module Dialog ───────────────────────────────────────────────────────
const isEditModuleVisible = ref(false)
const isEditingModule     = ref(false)
const editModuleData      = ref<{ id: number; title: string }>({ id: 0, title: '' })

const openEditModule = (mod: any) => {
  editModuleData.value = { id: mod.id, title: mod.title }
  isEditModuleVisible.value = true
}

const submitEditModule = async () => {
  if (!editModuleData.value.title.trim()) return
  isEditingModule.value = true
  try {
    await api.put(`/api/dashboard/modules/${editModuleData.value.id}`, { title: editModuleData.value.title })
    isEditModuleVisible.value = false
    await fetchCurriculum()
  } catch (e) { console.error(e) }
  finally { isEditingModule.value = false }
}

// ─── Delete Module ────────────────────────────────────────────────────────────
const moduleToDelete        = ref<any>(null)
const isDeleteModuleVisible = ref(false)
const isDeletingModule      = ref(false)

const confirmDeleteModule = (mod: any) => { moduleToDelete.value = mod; isDeleteModuleVisible.value = true }

const executeDeleteModule = async () => {
  isDeletingModule.value = true
  try {
    await api.delete(`/api/dashboard/modules/${moduleToDelete.value.id}`)
    isDeleteModuleVisible.value = false
    moduleToDelete.value = null
    await fetchCurriculum()
  } catch (e) { console.error(e) }
  finally { isDeletingModule.value = false }
}

// ─── Add Lesson Dialog ────────────────────────────────────────────────────────
const isAddLessonVisible   = ref(false)
const isAddingLesson       = ref(false)
const activeLessonModuleId = ref<number | null>(null)
const addLessonForm        = ref({ title: '', video_url: '', content: '', duration_minutes: null as number | null })

const openAddLesson = (moduleId: number) => {
  activeLessonModuleId.value = moduleId
  addLessonForm.value = { title: '', video_url: '', content: '', duration_minutes: null }
  isAddLessonVisible.value = true
}

const probeVideoDuration = (url: string): Promise<number | null> => {
  return new Promise((resolve) => {
    if (!url) { resolve(null); return }
    const video = document.createElement('video')
    video.preload = 'metadata'
    let settled = false
    const done = (val: number | null) => {
      if (settled) return
      settled = true
      video.removeAttribute('src')
      resolve(val)
    }
    video.onloadedmetadata = () => {
      const d = video.duration
      done(Number.isFinite(d) && d > 0 ? Math.round(d) : null)
    }
    video.onerror = () => done(null)
    setTimeout(() => done(null), 10000)
    video.src = url
  })
}

const isProbing = ref(false)

const onVideoUrlChange = async (type: 'add' | 'edit') => {
  const form = type === 'add' ? addLessonForm.value : editLessonData.value
  if (!form.video_url) return
  isProbing.value = true
  try {
    const seconds = await probeVideoDuration(form.video_url)
    if (seconds) form.duration_minutes = Math.ceil(seconds / 60)
  } catch (e) { console.error(e) }
  finally { isProbing.value = false }
}

const submitAddLesson = async () => {
  isAddingLesson.value = true
  try {
    let duration_seconds = addLessonForm.value.duration_minutes
      ? addLessonForm.value.duration_minutes * 60
      : null
    if (duration_seconds === null && addLessonForm.value.video_url)
      duration_seconds = await probeVideoDuration(addLessonForm.value.video_url)

    await api.post('/api/dashboard/lessons', {
      module_id:       activeLessonModuleId.value,
      title:           addLessonForm.value.title,
      video_url:       addLessonForm.value.video_url,
      content:         addLessonForm.value.content,
      duration_seconds,
    })
    isAddLessonVisible.value = false
    await fetchCurriculum()
  } catch (e) { console.error(e) }
  finally { isAddingLesson.value = false }
}

// ─── Edit Lesson Dialog ───────────────────────────────────────────────────────
const isEditLessonVisible = ref(false)
const isEditingLesson     = ref(false)
const editLessonData = ref<{ id: number; title: string; video_url: string; content: string; duration_minutes: number | null }>({
  id: 0, title: '', video_url: '', content: '', duration_minutes: null
})

const openEditLesson = (lesson: any) => {
  editLessonData.value = {
    id: lesson.id, title: lesson.title,
    video_url: lesson.video_url || '',
    content: lesson.content || '',
    duration_minutes: lesson.duration_minutes || null,
  }
  isEditLessonVisible.value = true
}

const submitEditLesson = async () => {
  isEditingLesson.value = true
  try {
    let duration_seconds = editLessonData.value.duration_minutes
      ? editLessonData.value.duration_minutes * 60
      : null
    if (duration_seconds === null && editLessonData.value.video_url)
      duration_seconds = await probeVideoDuration(editLessonData.value.video_url)

    await api.put(`/api/dashboard/lessons/${editLessonData.value.id}`, {
      title: editLessonData.value.title,
      video_url: editLessonData.value.video_url,
      content: editLessonData.value.content,
      duration_seconds,
    })
    isEditLessonVisible.value = false
    await fetchCurriculum()
  } catch (e) { console.error(e) }
  finally { isEditingLesson.value = false }
}

// ─── Delete Lesson ────────────────────────────────────────────────────────────
const lessonToDelete        = ref<any>(null)
const isDeleteLessonVisible = ref(false)
const isDeletingLesson      = ref(false)

const confirmDeleteLesson = (lesson: any) => { lessonToDelete.value = lesson; isDeleteLessonVisible.value = true }

const executeDeleteLesson = async () => {
  isDeletingLesson.value = true
  try {
    await api.delete(`/api/dashboard/lessons/${lessonToDelete.value.id}`)
    isDeleteLessonVisible.value = false
    lessonToDelete.value = null
    await fetchCurriculum()
  } catch (e) { console.error(e) }
  finally { isDeletingLesson.value = false }
}

// ─── Module Attachments ───────────────────────────────────────────────────────
const attachmentsMap        = ref<Record<number, any[]>>({})
const isUploadVisible       = ref(false)
const isUploading           = ref(false)
const uploadModuleId        = ref<number | null>(null)
const uploadFile            = ref<File | null>(null)
const uploadTitle           = ref('')
const attachmentToDelete    = ref<any>(null)
const isDeleteAttachVisible = ref(false)
const isDeletingAttachment  = ref(false)

const loadAttachments = async (moduleId: number) => {
  try {
    const res = await api.get(`/api/dashboard/modules/${moduleId}/attachments`)
    attachmentsMap.value[moduleId] = res.data
  } catch (e) { console.error(e) }
}

const openUploadDialog = (moduleId: number) => {
  uploadModuleId.value = moduleId
  uploadFile.value = null
  uploadTitle.value = ''
  isUploadVisible.value = true
}

const onFileChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  uploadFile.value = input.files?.[0] ?? null
  if (uploadFile.value && !uploadTitle.value)
    uploadTitle.value = uploadFile.value.name.replace(/\.pdf$/i, '')
}

const submitUpload = async () => {
  if (!uploadFile.value || !uploadModuleId.value) return
  isUploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', uploadFile.value)
    formData.append('title', uploadTitle.value || uploadFile.value.name)
    await api.post(`/api/dashboard/modules/${uploadModuleId.value}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    isUploadVisible.value = false
    await loadAttachments(uploadModuleId.value)
  } catch (e) { console.error(e) }
  finally { isUploading.value = false }
}

const confirmDeleteAttachment = (att: any) => {
  attachmentToDelete.value = att
  isDeleteAttachVisible.value = true
}

const executeDeleteAttachment = async () => {
  isDeletingAttachment.value = true
  try {
    await api.delete(`/api/dashboard/attachments/${attachmentToDelete.value.id}`)
    const modId = attachmentToDelete.value.module_id
    isDeleteAttachVisible.value = false
    attachmentToDelete.value = null
    await loadAttachments(modId)
  } catch (e) { console.error(e) }
  finally { isDeletingAttachment.value = false }
}

const formatSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// ─── Lesson PDF Attachments ───────────────────────────────────────────────────
const lessonAttachmentsMap      = ref<Record<number, any[]>>({})
const isLessonUploadVisible     = ref(false)
const isLessonUploading         = ref(false)
const uploadLessonId            = ref<number | null>(null)
const uploadLessonFile          = ref<File | null>(null)
const uploadLessonTitle         = ref('')
const lessonAttToDelete         = ref<any>(null)
const isDeleteLessonAttVisible  = ref(false)
const isDeletingLessonAtt       = ref(false)
const activeLessonPdfId         = ref<number | null>(null)   // which lesson's PDF panel is open

const loadLessonAttachments = async (lessonId: number) => {
  try {
    const res = await api.get(`/api/dashboard/lessons/${lessonId}/attachments`)
    lessonAttachmentsMap.value[lessonId] = res.data
  } catch (e) { console.error(e) }
}

const toggleLessonPdfPanel = async (lessonId: number) => {
  if (activeLessonPdfId.value === lessonId) {
    activeLessonPdfId.value = null
    return
  }
  activeLessonPdfId.value = lessonId
  if (!lessonAttachmentsMap.value[lessonId]) {
    await loadLessonAttachments(lessonId)
  }
}

const openLessonUploadDialog = (lessonId: number) => {
  uploadLessonId.value    = lessonId
  uploadLessonFile.value  = null
  uploadLessonTitle.value = ''
  isLessonUploadVisible.value = true
}

const onLessonFileChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  uploadLessonFile.value = input.files?.[0] ?? null
  if (uploadLessonFile.value && !uploadLessonTitle.value)
    uploadLessonTitle.value = uploadLessonFile.value.name.replace(/\.pdf$/i, '')
}

const submitLessonUpload = async () => {
  if (!uploadLessonFile.value || !uploadLessonId.value) return
  isLessonUploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', uploadLessonFile.value)
    formData.append('title', uploadLessonTitle.value || uploadLessonFile.value.name)
    await api.post(`/api/dashboard/lessons/${uploadLessonId.value}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    isLessonUploadVisible.value = false
    await loadLessonAttachments(uploadLessonId.value)
  } catch (e) { console.error(e) }
  finally { isLessonUploading.value = false }
}

const confirmDeleteLessonAtt = (att: any) => {
  lessonAttToDelete.value = att
  isDeleteLessonAttVisible.value = true
}

const executeDeleteLessonAtt = async () => {
  isDeletingLessonAtt.value = true
  try {
    await api.delete(`/api/dashboard/lesson-attachments/${lessonAttToDelete.value.id}`)
    const lessonId = lessonAttToDelete.value.lesson_id
    isDeleteLessonAttVisible.value = false
    lessonAttToDelete.value = null
    await loadLessonAttachments(lessonId)
  } catch (e) { console.error(e) }
  finally { isDeletingLessonAtt.value = false }
}
</script>

<template>
  <VCard class="pa-5">
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-3">
      <div>
        <h4 class="text-h5 font-weight-bold">{{ $t('Curriculum Builder') }}</h4>
        <p class="text-medium-emphasis text-body-2 mt-1">
          {{ $t('Drag the') }}
          <VIcon icon="tabler-grip-vertical" size="14" class="mx-1" />
          {{ $t('handle to reorder modules and lessons') }}
        </p>
      </div>
      <div class="d-flex align-center gap-2">
        <VFadeTransition>
          <VChip v-if="isSavingOrder" size="small" color="warning" prepend-icon="tabler-loader-2">
            {{ $t('Saving order…') }}
          </VChip>
        </VFadeTransition>
        <VBtn prepend-icon="tabler-plus" color="primary" @click="isAddModuleVisible = true">
          {{ $t('Add Module') }}
        </VBtn>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="d-flex justify-center pa-10">
      <VProgressCircular indeterminate color="primary" size="48" />
    </div>

    <!-- Empty State -->
    <VAlert v-else-if="modules.length === 0" color="info" variant="tonal" prepend-icon="tabler-info-circle">
      {{ $t('No modules yet. Click "Add Module" to get started.') }}
    </VAlert>

    <!-- ── Draggable Modules List ──────────────────────────────────────────── -->
    <div v-else ref="modulesParent" class="d-flex flex-column gap-3">
      <div
        v-for="(mod, mIdx) in modules"
        :key="mod.id"
        class="rounded-lg border module-card"
        style="border-color: rgba(var(--v-border-color), var(--v-border-opacity))"
      >
        <!-- Module Header -->
        <div
          class="d-flex align-center gap-3 pa-4 rounded-t-lg cursor-pointer"
          style="background: rgba(var(--v-theme-surface-variant), 0.4)"
          @click="toggleCollapse(mod.id)"
        >
          <!-- Drag Handle -->
          <div
            class="module-drag-handle d-flex align-center justify-center rounded"
            style="cursor: grab; width:28px; height:28px; background: rgba(var(--v-theme-primary), 0.08)"
            @click.stop
          >
            <VIcon icon="tabler-grip-vertical" size="18" color="primary" />
          </div>

          <!-- Order badge -->
          <VChip size="x-small" color="primary" variant="tonal" label>{{ mIdx + 1 }}</VChip>

          <!-- Title -->
          <span class="font-weight-semibold text-body-1 flex-grow-1">{{ mod.title }}</span>

          <!-- Meta chips -->
          <VChip size="x-small" variant="tonal" color="secondary" class="me-1">
            {{ mod.lessons?.length || 0 }} {{ $t('lessons') }}
          </VChip>

          <!-- Module actions -->
          <VBtn
            icon="tabler-edit"
            size="x-small"
            variant="text"
            color="info"
            @click.stop="openEditModule(mod)"
          />
          <VBtn
            icon="tabler-trash"
            size="x-small"
            variant="text"
            color="error"
            @click.stop="confirmDeleteModule(mod)"
          />

          <!-- Collapse toggle -->
          <VIcon
            :icon="collapsedMap[mod.id] ? 'tabler-chevron-down' : 'tabler-chevron-up'"
            size="18"
            class="ms-1"
          />
        </div>

        <!-- Module Body (collapsible) -->
        <VExpandTransition>
          <div v-if="!collapsedMap[mod.id]" class="pa-4">

            <VDivider class="mb-4" />

            <!-- ── Draggable Lessons List ───────────────────────────── -->
            <div
              v-if="mod.items && mod.items.length > 0"
              :ref="el => initLessonDrag(el as HTMLElement, mod)"
              class="d-flex flex-column gap-2 mb-4"
            >
              <div
                v-for="(item, lIdx) in mod.items"
                :key="`${item.item_type}-${item.id}`"
                class="d-flex align-center gap-3 rounded-lg pa-3 lesson-item border"
                style="border-color: rgba(var(--v-border-color), var(--v-border-opacity))"
              >
                <!-- Lesson Drag Handle -->
                <div
                  class="lesson-drag-handle d-flex align-center justify-center rounded"
                  style="cursor: grab; width:24px; height:24px; flex-shrink:0; background: rgba(var(--v-theme-secondary), 0.08)"
                  @click.stop
                >
                  <VIcon icon="tabler-grip-vertical" size="14" color="secondary" />
                </div>

                <!-- Icon -->
                <VAvatar :color="item.item_type === 'quiz' ? 'secondary' : 'primary'" variant="tonal" size="32" rounded>
                  <VIcon :icon="item.item_type === 'quiz' ? 'tabler-clipboard-check' : 'tabler-play-circle'" size="16" />
                </VAvatar>

                <!-- Content -->
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex align-center gap-2">
                    <span class="font-weight-medium text-body-2 text-truncate">{{ item.title }}</span>
                    <VChip v-if="item.item_type === 'quiz'" size="x-small" color="secondary">Quiz</VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis text-truncate">
                    {{ item.item_type === 'lesson' ? item.video_url : `Passing Score: ${item.passing_score}%` }}
                  </div>
                </div>

                <!-- Actions -->
                <VBtn
                  v-if="item.item_type === 'lesson'"
                  icon="tabler-edit"
                  variant="text"
                  size="x-small"
                  color="info"
                  @click.stop="openEditLesson(item)"
                />
                <VBtn
                  v-if="item.item_type === 'lesson'"
                  :icon="activeLessonPdfId === item.id ? 'tabler-file-type-pdf' : 'tabler-file-type-pdf'"
                  variant="tonal"
                  size="x-small"
                  :color="activeLessonPdfId === item.id ? 'error' : 'default'"
                  @click.stop="toggleLessonPdfPanel(item.id)"
                >
                  <VIcon icon="tabler-file-type-pdf" />
                  <VTooltip activator="parent" location="top">{{ $t('Lesson PDFs') }}</VTooltip>
                </VBtn>
                <VBtn
                  v-if="item.item_type === 'lesson'"
                  icon="tabler-trash"
                  variant="text"
                  size="x-small"
                  color="error"
                  @click.stop="confirmDeleteLesson(item)"
                />
              </div>

              <!-- Lesson PDF Panel (inline, below lesson row) -->
              <VExpandTransition>
                <div
                  v-if="item.item_type === 'lesson' && activeLessonPdfId === item.id"
                  class="rounded-lg pa-3 mb-1 ms-10"
                  style="background: rgba(var(--v-theme-error), 0.04); border: 1px dashed rgba(var(--v-theme-error), 0.3)"
                >
                  <div class="d-flex justify-space-between align-center mb-2">
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-paperclip" size="16" color="error" />
                      <span class="text-caption font-weight-semibold">{{ $t('PDF Files') }}</span>
                      <VChip size="x-small" color="error" variant="tonal">
                        {{ (lessonAttachmentsMap[item.id] || []).length }}
                      </VChip>
                    </div>
                    <VBtn
                      size="x-small"
                      color="error"
                      variant="tonal"
                      prepend-icon="tabler-upload"
                      @click.stop="openLessonUploadDialog(item.id)"
                    >
                      {{ $t('Upload PDF') }}
                    </VBtn>
                  </div>

                  <div v-if="lessonAttachmentsMap[item.id] && lessonAttachmentsMap[item.id].length > 0">
                    <div
                      v-for="att in lessonAttachmentsMap[item.id]"
                      :key="att.id"
                      class="d-flex align-center gap-2 rounded pa-2 mb-1"
                      style="background: rgba(var(--v-theme-surface), 0.8)"
                    >
                      <VIcon icon="tabler-file-type-pdf" size="16" color="error" />
                      <div class="flex-grow-1">
                        <div class="text-caption font-weight-medium">{{ att.title }}</div>
                        <div class="text-caption text-medium-emphasis">{{ formatSize(att.file_size) }}</div>
                      </div>
                      <VBtn
                        icon="tabler-download"
                        variant="text"
                        size="x-small"
                        color="primary"
                        :href="`${API_STORAGE}/storage/${att.file_path}`"
                        target="_blank"
                      />
                      <VBtn
                        icon="tabler-trash"
                        variant="text"
                        size="x-small"
                        color="error"
                        @click.stop="confirmDeleteLessonAtt({ ...att, lesson_id: item.id })"
                      />
                    </div>
                  </div>
                  <p v-else class="text-caption text-medium-emphasis">{{ $t('No PDFs attached to this lesson yet.') }}</p>
                </div>
              </VExpandTransition>
            </div>

            <p v-else class="text-medium-emphasis text-body-2 mb-4">
              {{ $t('No items in this module yet.') }}
            </p>

            <!-- Add Buttons -->
            <div class="d-flex gap-2 mb-4">
              <VBtn size="small" variant="outlined" color="primary" prepend-icon="tabler-plus" @click="openAddLesson(mod.id)">
                {{ $t('Add Lesson') }}
              </VBtn>
              <VBtn size="small" variant="outlined" color="secondary" prepend-icon="tabler-plus" to="/quizzes/exams">
                {{ $t('Manage Quizzes') }}
              </VBtn>
            </div>

            <VDivider class="my-4" />

            <!-- ── PDF Attachments ─────────────────────────────────── -->
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-paperclip" size="18" color="warning" />
                <span class="text-body-2 font-weight-semibold">{{ $t('PDF Attachments') }}</span>
                <VChip size="x-small" variant="tonal" color="warning">
                  {{ (attachmentsMap[mod.id] || []).length }}
                </VChip>
              </div>
              <VBtn size="small" variant="tonal" color="warning" prepend-icon="tabler-upload" @click="openUploadDialog(mod.id)">
                {{ $t('Upload PDF') }}
              </VBtn>
            </div>

            <div v-if="attachmentsMap[mod.id] && attachmentsMap[mod.id].length > 0">
              <div
                v-for="att in attachmentsMap[mod.id]"
                :key="att.id"
                class="d-flex align-center gap-3 rounded-lg pa-3 mb-2 border"
                style="border-color: rgba(var(--v-border-color), var(--v-border-opacity))"
              >
                <VAvatar color="error" variant="tonal" size="30" rounded>
                  <VIcon icon="tabler-file-type-pdf" size="16" />
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="text-body-2 font-weight-medium">{{ att.title }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatSize(att.file_size) }}</div>
                </div>
                <VBtn
                  icon="tabler-download"
                  variant="text"
                  size="x-small"
                  color="primary"
                  :href="`${API_STORAGE}/storage/${att.file_path}`"
                  target="_blank"
                />
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  size="x-small"
                  color="error"
                  @click="confirmDeleteAttachment({ ...att, module_id: mod.id })"
                />
              </div>
            </div>
            <p v-else class="text-caption text-medium-emphasis">{{ $t('No attachments yet.') }}</p>

          </div>
        </VExpandTransition>
      </div>
    </div>

    <!-- ─── Dialogs ──────────────────────────────────────────────────────────── -->

    <!-- Add Module -->
    <VDialog v-model="isAddModuleVisible" max-width="480">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-layout-list" color="primary" />
            {{ $t('Add Module') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <VTextField v-model="addModuleTitle" :label="$t('Module Title')" variant="outlined" autofocus @keyup.enter="submitAddModule" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isAddModuleVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="primary" :loading="isAddingModule" :disabled="!addModuleTitle.trim()" @click="submitAddModule">{{ $t('Save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Module -->
    <VDialog v-model="isEditModuleVisible" max-width="480">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-edit" color="info" />
            {{ $t('Rename Module') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <VTextField v-model="editModuleData.title" :label="$t('Module Title')" variant="outlined" autofocus @keyup.enter="submitEditModule" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isEditModuleVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="info" :loading="isEditingModule" :disabled="!editModuleData.title.trim()" @click="submitEditModule">{{ $t('Save Changes') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Module -->
    <VDialog v-model="isDeleteModuleVisible" max-width="420">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-error">
            <VIcon icon="tabler-alert-triangle" color="error" />
            {{ $t('Delete Module') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <p>{{ $t('Are you sure you want to delete module') }} <strong>{{ moduleToDelete?.title }}</strong>?</p>
          <VAlert type="warning" variant="tonal" density="compact" class="mt-3">
            {{ $t('All lessons inside this module will also be deleted.') }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isDeleteModuleVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="error" :loading="isDeletingModule" @click="executeDeleteModule">{{ $t('Yes, Delete') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Add Lesson -->
    <VDialog v-model="isAddLessonVisible" max-width="560">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-video" color="primary" />
            {{ $t('Add Lesson') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="addLessonForm.title" :label="$t('Lesson Title')" variant="outlined" autofocus />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="addLessonForm.video_url"
                :label="$t('Video URL')"
                placeholder="https://..."
                type="url"
                variant="outlined"
                prepend-inner-icon="tabler-brand-youtube"
                @blur="onVideoUrlChange('add')"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="addLessonForm.duration_minutes"
                :label="$t('Duration (minutes)')"
                type="number" min="0" variant="outlined"
                prepend-inner-icon="tabler-clock"
                :loading="isProbing"
                :messages="isProbing ? [$t('Probing video URL for duration...')] : []"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="addLessonForm.content" :label="$t('Notes (optional)')" variant="outlined" rows="3" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isAddLessonVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="primary" :loading="isAddingLesson" :disabled="!addLessonForm.title || !addLessonForm.video_url" @click="submitAddLesson">{{ $t('Save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Lesson -->
    <VDialog v-model="isEditLessonVisible" max-width="560">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-edit" color="info" />
            {{ $t('Edit Lesson') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="editLessonData.title" :label="$t('Lesson Title')" variant="outlined" autofocus />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="editLessonData.video_url"
                :label="$t('Video URL')"
                type="url" variant="outlined"
                prepend-inner-icon="tabler-brand-youtube"
                @blur="onVideoUrlChange('edit')"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="editLessonData.duration_minutes"
                :label="$t('Duration (minutes)')"
                type="number" min="0" variant="outlined"
                prepend-inner-icon="tabler-clock"
                :loading="isProbing"
                :messages="isProbing ? [$t('Probing video URL for duration...')] : []"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="editLessonData.content" :label="$t('Notes (optional)')" variant="outlined" rows="3" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isEditLessonVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="info" :loading="isEditingLesson" :disabled="!editLessonData.title || !editLessonData.video_url" @click="submitEditLesson">{{ $t('Save Changes') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Lesson -->
    <VDialog v-model="isDeleteLessonVisible" max-width="400">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-error">
            <VIcon icon="tabler-trash" color="error" />
            {{ $t('Delete Lesson') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <p>{{ $t('Delete lesson') }} <strong>{{ lessonToDelete?.title }}</strong>?</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isDeleteLessonVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="error" :loading="isDeletingLesson" @click="executeDeleteLesson">{{ $t('Yes, Delete') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Upload PDF -->
    <VDialog v-model="isUploadVisible" max-width="500">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-file-type-pdf" color="error" />
            {{ $t('Upload PDF Attachment') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField v-model="uploadTitle" :label="$t('Title (optional)')" variant="outlined" :placeholder="$t('Leave blank to use filename')" />
            </VCol>
            <VCol cols="12">
              <div
                class="rounded-lg border-2 border-dashed pa-6 text-center cursor-pointer"
                style="border-color: rgba(var(--v-theme-warning), 0.5); background: rgba(var(--v-theme-warning), 0.04)"
                @click="($refs.pdfInput as HTMLInputElement).click()"
              >
                <VIcon icon="tabler-upload" size="36" color="warning" class="mb-2" />
                <p class="text-body-2 font-weight-semibold text-warning">
                  {{ uploadFile ? uploadFile.name : $t('Click to choose PDF file') }}
                </p>
                <p v-if="uploadFile" class="text-caption text-medium-emphasis">{{ formatSize(uploadFile.size) }}</p>
                <p v-else class="text-caption text-medium-emphasis">PDF • {{ $t('Max 20MB') }}</p>
                <input ref="pdfInput" type="file" accept=".pdf,application/pdf" class="d-none" @change="onFileChange" />
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isUploadVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="warning" :loading="isUploading" :disabled="!uploadFile" prepend-icon="tabler-upload" @click="submitUpload">{{ $t('Upload') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Module Attachment -->
    <VDialog v-model="isDeleteAttachVisible" max-width="400">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-error">
            <VIcon icon="tabler-trash" color="error" />
            {{ $t('Delete Attachment') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <p>{{ $t('Delete attachment') }} <strong>{{ attachmentToDelete?.title }}</strong>?</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isDeleteAttachVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="error" :loading="isDeletingAttachment" @click="executeDeleteAttachment">{{ $t('Yes, Delete') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Upload Lesson PDF -->
    <VDialog v-model="isLessonUploadVisible" max-width="500">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-file-type-pdf" color="error" />
            {{ $t('Upload Lesson PDF') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="uploadLessonTitle"
                :label="$t('Title (optional)')"
                variant="outlined"
                :placeholder="$t('Leave blank to use filename')"
              />
            </VCol>
            <VCol cols="12">
              <div
                class="rounded-lg border-2 border-dashed pa-6 text-center cursor-pointer"
                style="border-color: rgba(var(--v-theme-error), 0.5); background: rgba(var(--v-theme-error), 0.04)"
                @click="($refs.lessonPdfInput as HTMLInputElement).click()"
              >
                <VIcon icon="tabler-upload" size="36" color="error" class="mb-2" />
                <p class="text-body-2 font-weight-semibold text-error">
                  {{ uploadLessonFile ? uploadLessonFile.name : $t('Click to choose PDF file') }}
                </p>
                <p v-if="uploadLessonFile" class="text-caption text-medium-emphasis">{{ formatSize(uploadLessonFile.size) }}</p>
                <p v-else class="text-caption text-medium-emphasis">PDF • {{ $t('Max 20MB') }}</p>
                <input ref="lessonPdfInput" type="file" accept=".pdf,application/pdf" class="d-none" @change="onLessonFileChange" />
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isLessonUploadVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn
            color="error"
            :loading="isLessonUploading"
            :disabled="!uploadLessonFile"
            prepend-icon="tabler-upload"
            @click="submitLessonUpload"
          >
            {{ $t('Upload') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Lesson Attachment -->
    <VDialog v-model="isDeleteLessonAttVisible" max-width="400">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-error">
            <VIcon icon="tabler-trash" color="error" />
            {{ $t('Delete Lesson PDF') }}
          </VCardTitle>
        </VCardItem>
        <VCardText>
          <p>{{ $t('Delete attachment') }} <strong>{{ lessonAttToDelete?.title }}</strong>?</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isDeleteLessonAttVisible = false">{{ $t('Cancel') }}</VBtn>
          <VBtn color="error" :loading="isDeletingLessonAtt" @click="executeDeleteLessonAtt">{{ $t('Yes, Delete') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

  </VCard>
</template>

<style scoped>
.module-card {
  transition: box-shadow 0.2s;
}
.module-card:hover {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.08);
}
.module-drag-handle:active,
.lesson-drag-handle:active {
  cursor: grabbing !important;
}
/* formkit drag placeholder style */
:global([data-is-drag-placeholder]) {
  opacity: 0.4;
  background: rgba(var(--v-theme-primary), 0.06) !important;
  border: 2px dashed rgba(var(--v-theme-primary), 0.3) !important;
  border-radius: 8px;
}
</style>
