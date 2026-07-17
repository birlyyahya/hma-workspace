import Uppy from '@uppy/core';
import AwsS3 from '@uppy/aws-s3';

/**
 * Uploader project files: S3 multipart langsung browser → MinIO.
 *
 * Laravel hanya control plane (initiate / sign / complete / abort) — lihat
 * ProjectFileUploadController. Presigned URL part di-sign secara batch
 * (SIGN_BATCH part per request) supaya tidak menabrak throttle endpoint sign.
 *
 * Dipakai dari komponen Volt file-manager lewat window.ProjectFileUploader.
 */

const SIGN_BATCH = 10;

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
    document.querySelector('input[name="_token"]')?.value ??
    '';

async function jsonRequest(method, url, body = null) {
    const response = await fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
            ...(body !== null ? { 'Content-Type': 'application/json' } : {}),
        },
        credentials: 'same-origin',
        body: body !== null ? JSON.stringify(body) : undefined,
    });

    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validation = json?.errors ? Object.values(json.errors).flat().join('\n') : null;
        throw new Error(validation || json?.message || `Request gagal (HTTP ${response.status}).`);
    }

    return json;
}

export function createProjectFileUploader({ projectId, partSize, getFolderId, onProgress, onSuccess, onError }) {
    const base = `/projects/${projectId}/files/uploads`;

    // Cache presigned URL per upload: `${uploadId}:${partNumber}` => url.
    const signedUrls = new Map();

    // Folder tujuan per upload, ditangkap saat initiate — dikirim lagi saat
    // complete (penempatan folder dicatat di server setelah doc id diketahui).
    const folderIds = new Map();

    const uppy = new Uppy({
        autoProceed: true,
        allowMultipleUploadBatches: true,
    });

    uppy.use(AwsS3, {
        shouldUseMultipart: true,
        limit: 4,
        getChunkSize: () => partSize,

        async createMultipartUpload(file) {
            const folderId = getFolderId() ?? null;
            const json = await jsonRequest('POST', base, {
                filename: file.name,
                size: file.size,
                mime: file.type || 'application/octet-stream',
                folder_id: folderId,
            });

            folderIds.set(json.upload_id, folderId);

            return { uploadId: json.upload_id, key: json.key };
        },

        async signPart(file, { uploadId, key, partNumber }) {
            const cacheKey = `${uploadId}:${partNumber}`;

            if (!signedUrls.has(cacheKey)) {
                const partNumbers = [];
                for (let n = partNumber; n < partNumber + SIGN_BATCH; n++) {
                    partNumbers.push(n);
                }

                const json = await jsonRequest('POST', `${base}/${encodeURIComponent(uploadId)}/sign`, {
                    key,
                    part_numbers: partNumbers,
                });

                for (const [n, url] of Object.entries(json.urls ?? {})) {
                    signedUrls.set(`${uploadId}:${n}`, url);
                }
            }

            const url = signedUrls.get(cacheKey);

            if (!url) {
                throw new Error(`Presigned URL part ${partNumber} tidak diterima dari server.`);
            }

            return { url };
        },

        async completeMultipartUpload(file, { uploadId, key, parts }) {
            const json = await jsonRequest('POST', `${base}/${encodeURIComponent(uploadId)}/complete`, {
                key,
                filename: file.name,
                folder_id: folderIds.get(uploadId) ?? null,
                parts: parts.map((part) => ({
                    part_number: part.PartNumber,
                    etag: part.ETag,
                })),
            });

            folderIds.delete(uploadId);

            for (const cacheKey of signedUrls.keys()) {
                if (cacheKey.startsWith(`${uploadId}:`)) signedUrls.delete(cacheKey);
            }

            return { location: json.key };
        },

        async abortMultipartUpload(file, { uploadId, key }) {
            await jsonRequest('DELETE', `${base}/${encodeURIComponent(uploadId)}`, { key });
        },

        async listParts() {
            return [];
        },
    });

    uppy.on('upload-progress', (file, progress) => {
        if (!file || !progress?.bytesTotal) return;
        onProgress?.(file.id, Math.round((progress.bytesUploaded / progress.bytesTotal) * 100), file.name);
    });

    uppy.on('upload-success', (file) => {
        if (!file) return;
        onSuccess?.(file.id, file.name);
        uppy.removeFile(file.id);
    });

    uppy.on('upload-error', (file, error) => {
        if (!file) return;
        onError?.(file.id, file.name, error?.message ?? 'Upload gagal.');
        uppy.removeFile(file.id);
    });

    return {
        uppy,

        addFiles(fileList) {
            const added = [];

            for (const file of fileList) {
                try {
                    const id = uppy.addFile({
                        name: file.name,
                        type: file.type,
                        data: file,
                    });
                    added.push({ id, name: file.name, size: file.size });
                } catch (error) {
                    onError?.(null, file.name, error?.message ?? 'File tidak bisa ditambahkan.');
                }
            }

            return added;
        },

        cancel(fileId) {
            uppy.removeFile(fileId);
        },
    };
}

window.ProjectFileUploader = { create: createProjectFileUploader };

/**
 * Alpine component untuk drop area di Volt file-manager. Didefinisikan di
 * sini (bukan @script) supaya sudah tersedia sebelum Alpine memproses DOM;
 * $wire adalah magic Livewire yang tersedia di dalam DOM komponen.
 */
window.projectFileManagerUploader = (projectId, partSize) => ({
    uploads: [],
    uploader: null,

    init() {
        this.uploader = createProjectFileUploader({
            projectId,
            partSize,
            getFolderId: () => this.$wire.get('currentFolderId'),
            onProgress: (id, progress) => this.patch(id, { progress, status: 'uploading' }),
            onSuccess: (id) => {
                this.patch(id, { progress: 100, status: 'done' });
                setTimeout(() => this.dismiss(id), 4000);
                this.$wire.dispatch('file-uploaded');
            },
            onError: (id, name, message) => {
                if (id === null) {
                    this.uploads = [{ id: `err_${Date.now()}`, name, progress: 0, status: 'error', message }, ...this.uploads];
                    return;
                }
                this.patch(id, { status: 'error', message });
            },
        });
    },

    addFiles(fileList) {
        const added = this.uploader.addFiles(fileList);
        this.uploads = [
            ...added.map((file) => ({ id: file.id, name: file.name, progress: 0, status: 'uploading', message: null })),
            ...this.uploads,
        ];
    },

    patch(id, changes) {
        this.uploads = this.uploads.map((item) => (item.id === id ? { ...item, ...changes } : item));
    },

    cancel(id) {
        try {
            this.uploader.cancel(id);
        } catch (_) {
            // file mungkin sudah selesai/terhapus dari antrean
        }
        this.dismiss(id);
    },

    dismiss(id) {
        this.uploads = this.uploads.filter((item) => item.id !== id);
    },
});
