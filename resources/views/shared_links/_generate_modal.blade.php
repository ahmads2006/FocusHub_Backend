<!-- Owner Dashboard Generate Link Modal Mockup -->
<div class="modal fade" id="generateSharedLinkModal" tabindex="-1" aria-labelledby="generateSharedLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="generateSharedLinkModalLabel">
                    <i class="bi bi-link-45deg me-2"></i> إنشاء رابط مشاركة آمن
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form id="generateSharedLinkForm">
                    <input type="hidden" name="shareable_id" id="shareable_id" value="">
                    <input type="hidden" name="shareable_type" id="shareable_type" value="">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">إعدادات الأمان <i class="bi bi-shield-lock text-success ms-1"></i></label>
                        
                        <div class="card border-0 shadow-sm mt-2">
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="auto_rotate" name="auto_rotate" style="cursor: pointer;">
                                    <label class="form-check-label ms-2 fw-medium" for="auto_rotate" style="cursor: pointer;">
                                        تفعيل التدوير التلقائي (Auto-Rotate)
                                        <div class="text-muted small fw-normal mt-1" style="font-size: 0.8rem;">
                                            سيرتبط الرابط بأول جهاز يفتحه وسيتم تدمير الرابط الأصلي لمنع إعادة توجيهه.
                                        </div>
                                    </label>
                                </div>

                                <div class="form-floating mb-2 mt-3">
                                    <input type="number" class="form-control" id="max_access" name="max_access" placeholder="عدد مرات الفتح" min="1">
                                    <label for="max_access">الحد الأقصى لمرات الفتح (اختياري)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">إعدادات إضافية (اختياري)</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="expires_in" name="expires_in" placeholder="الصلاحية بالساعات" min="1">
                                    <label for="expires_in">الصلاحية (بالساعات)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="permission" name="permission">
                                        <option value="view">عرض فقط</option>
                                        <option value="download">عرض وتنزيل</option>
                                    </select>
                                    <label for="permission">الصلاحيات</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Result Container (Hidden initially) -->
                <div id="sharedLinkResult" class="d-none mt-4">
                    <hr>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <span id="resultMessage" class="fw-bold"></span>
                    </div>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control bg-white" id="generatedLinkInput" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="copyLinkBtn" onclick="copySharedLink()">
                            <i class="bi bi-clipboard"></i> نسخ
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-0 py-3">
                <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" id="btnGenerateLink" onclick="submitGenerateLink()">
                    <i class="bi bi-magic me-1"></i> إنشاء الرابط
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts for the Modal -->
<script>
    function openShareModal(id, type) {
        document.getElementById('shareable_id').value = id;
        document.getElementById('shareable_type').value = type;
        
        // Reset form
        document.getElementById('generateSharedLinkForm').reset();
        document.getElementById('sharedLinkResult').classList.add('d-none');
        document.getElementById('btnGenerateLink').disabled = false;
        
        var modal = new bootstrap.Modal(document.getElementById('generateSharedLinkModal'));
        modal.show();
    }

    function submitGenerateLink() {
        const btn = document.getElementById('btnGenerateLink');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> جاري الإنشاء...';

        const formData = new FormData(document.getElementById('generateSharedLinkForm'));
        const autoRotate = document.getElementById('auto_rotate').checked ? 1 : 0;
        
        // Build JSON payload
        const payload = {
            shareable_id: formData.get('shareable_id'),
            shareable_type: formData.get('shareable_type'),
            auto_rotate: autoRotate,
            _token: '{{ csrf_token() }}'
        };

        if(formData.get('max_access')) payload.max_access = formData.get('max_access');
        if(formData.get('expires_in')) payload.expires_in = formData.get('expires_in');
        if(formData.get('permission')) payload.permission = formData.get('permission');

        fetch('{{ route("share.generate") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('sharedLinkResult').classList.remove('d-none');
                document.getElementById('resultMessage').textContent = data.message;
                document.getElementById('generatedLinkInput').value = data.url;
                btn.innerHTML = '<i class="bi bi-check2-all me-1"></i> تم';
            } else {
                alert(data.message || 'حدث خطأ أثناء إنشاء الرابط.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-magic me-1"></i> إنشاء الرابط';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-magic me-1"></i> إنشاء الرابط';
        });
    }

    function copySharedLink() {
        const input = document.getElementById('generatedLinkInput');
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices
        
        navigator.clipboard.writeText(input.value).then(() => {
            const copyBtn = document.getElementById('copyLinkBtn');
            const originalHtml = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="bi bi-check-lg text-success"></i> تم النسخ';
            copyBtn.classList.add('border-success');
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.classList.remove('border-success');
            }, 2000);
        });
    }
</script>
