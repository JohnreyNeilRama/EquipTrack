@extends('layouts.admin')

@section('title', 'EquipTrack - Equipment Management')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}?v={{ filemtime(public_path('admin/css/adminequipment.css')) }}">
<style>
    .eq-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-available { background-color: #dcfce7; color: #15803d; }
    .status-unavailable { background-color: #fee2e2; color: #b91c1c; }
    .status-on-hold { background-color: #ffedd5; color: #c2410c; }
    .status-under-maintenance { background-color: #fef3c7; color: #b45309; }

    .eq-class-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .class-expensive { background-color: #ede9fe; color: #6d28d9; }
    .class-not-expensive { background-color: #e2e8f0; color: #475569; }
    .dark-theme .class-expensive { background-color: rgba(139, 92, 246, 0.18); color: #c4b5fd; }
    .dark-theme .class-not-expensive { background-color: rgba(148, 163, 184, 0.15); color: #cbd5e1; }


    /* ------------------------------------------------------------------
       Add / Edit Equipment form: same layout as the Department Equipment
       form (departments/css/equipment.css). Scoped to #equipmentModal so the
       shared adminequipment.css (loaded by other pages) and the Add
       Category modal keep their current look. Header and footer stay put;
       only the body scrolls on short screens.
       ------------------------------------------------------------------ */
    #equipmentModal {
        align-items: center;
        padding: 20px;
        overflow: hidden;
    }
    #equipmentModal .eq-modal-card {
        background-color: var(--card-bg) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 20px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25) !important;
        max-width: 600px !important;
        max-height: 90vh;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #equipmentModal .eq-modal-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 22px 24px 18px;
        border-bottom: 1px solid var(--border-color);
    }
    #equipmentModal .eq-modal-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background-color: var(--primary-light);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    #equipmentModal .eq-modal-heading {
        flex: 1;
        min-width: 0;
    }
    #equipmentModal .eq-modal-title {
        margin: 0;
        font-size: var(--fs-modal-title);
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    #equipmentModal .eq-modal-subtitle {
        margin: 2px 0 0;
        font-size: var(--fs-label);
        color: var(--text-muted);
    }
    #equipmentModal .modal-close {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1px solid var(--border-color);
        background: var(--bg-color);
        color: var(--text-muted);
        font-size: 18px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
    }
    #equipmentModal .modal-close:hover {
        background-color: var(--primary-light);
        color: var(--primary-color);
    }
    #equipmentModal .eq-modal-form {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    #equipmentModal .eq-modal-body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 20px 24px 24px;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    #equipmentModal .eq-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 24px;
        border-top: 1px solid var(--border-color);
        background-color: var(--card-bg);
    }
    #equipmentModal .eq-modal-footnote {
        margin: 0 auto 0 0;
        font-size: var(--fs-small);
        color: var(--text-muted);
    }

    /* Sections and fields */
    #equipmentModal .eq-form-section {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    #equipmentModal .eq-form-section-title {
        margin: 0;
        font-size: var(--fs-small);
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    #equipmentModal .form-group-flat {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
        margin-bottom: 0;
    }
    #equipmentModal .form-group-flat label {
        font-size: var(--fs-label);
        font-weight: 600;
        color: var(--text-main);
    }
    #equipmentModal .req {
        color: var(--danger-color);
        margin-left: 2px;
    }
    #equipmentModal .form-control-flat {
        width: 100%;
        height: 42px;
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background-color: var(--bg-color);
        color: var(--text-main);
        font-family: inherit;
        font-size: var(--fs-body);
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    #equipmentModal .form-control-flat::placeholder {
        color: var(--text-muted);
        opacity: 0.75;
    }
    #equipmentModal .form-control-flat:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(56, 85, 133, 0.15);
    }
    #equipmentModal .form-control-flat.mono {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }
    #equipmentModal .form-hint {
        margin: 0;
        font-size: var(--fs-small);
        color: var(--text-muted);
        line-height: 1.4;
    }
    #equipmentModal .form-row-flat {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    /* Select with a chevron that doesn't block clicks ("select-wrapper" is the
       class global.css exempts from its dark-theme arrow) */
    #equipmentModal .select-wrapper {
        position: relative;
    }
    #equipmentModal .select-wrapper select {
        appearance: none;
        -webkit-appearance: none;
        padding-right: 38px;
        cursor: pointer;
    }
    #equipmentModal .select-wrapper i {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: var(--text-muted);
        font-size: var(--fs-small);
    }

    /* Footer buttons */
    #equipmentModal .btn-submit-request,
    #equipmentModal .btn-modal-secondary {
        width: auto;
        height: 42px;
        padding: 0 20px;
        border-radius: 12px;
        font-family: inherit;
        font-size: var(--fs-body);
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        white-space: nowrap;
        transform: none;
        transition: background-color 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s;
    }
    #equipmentModal .btn-submit-request {
        min-width: 132px;
        background-color: #385585;
        color: #ffffff;
        border: none;
        box-shadow: 0 4px 10px rgba(56, 85, 133, 0.2);
    }
    #equipmentModal .btn-submit-request:hover {
        background-color: #2f477a;
    }
    #equipmentModal .btn-modal-secondary {
        background-color: transparent;
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }
    #equipmentModal .btn-modal-secondary:hover {
        background-color: var(--bg-color);
    }
    #equipmentModal .btn-submit-request:focus-visible,
    #equipmentModal .btn-modal-secondary:focus-visible,
    #equipmentModal .modal-close:focus-visible,
    #equipmentModal .btn-apply-url:focus-visible,
    #equipmentModal .btn-preview-action:focus-visible,
    #equipmentModal .image-dropzone:focus-within {
        outline: none;
        box-shadow: 0 0 0 3px rgba(56, 85, 133, 0.35);
    }

    /* Photo uploader */
    #equipmentModal .image-upload-wrapper {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 0;
        border: none;
        border-radius: 0;
        background: none;
    }
    #equipmentModal .image-dropzone {
        position: relative;
        border: 2px dashed var(--border-color);
        border-radius: 14px;
        padding: 18px 20px;
        cursor: pointer;
        background-color: var(--bg-color);
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 14px;
        text-align: left;
        transform: none;
        transition: border-color 0.2s, background-color 0.2s;
    }
    #equipmentModal .image-dropzone:hover,
    #equipmentModal .image-dropzone.dragover {
        border-color: var(--primary-color);
        background-color: var(--primary-light);
        transform: none;
    }
    #equipmentModal .dropzone-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    #equipmentModal .dropzone-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    #equipmentModal .dropzone-text .main-text {
        font-size: var(--fs-label);
        font-weight: 600;
        color: var(--text-main);
    }
    #equipmentModal .dropzone-text .browse-link {
        color: var(--primary-color);
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    #equipmentModal .dropzone-text .sub-text {
        font-size: var(--fs-small);
        color: var(--text-muted);
    }
    #equipmentModal .image-url-alternative {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    #equipmentModal .or-separator {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: var(--fs-small);
        font-weight: 500;
        letter-spacing: normal;
        text-transform: none;
        color: var(--text-muted);
    }
    #equipmentModal .url-input-container input {
        flex: 1;
        height: 42px;
        border-radius: 10px;
    }
    #equipmentModal .btn-apply-url {
        height: 42px;
        padding: 0 18px;
        border-radius: 10px;
        background-color: var(--primary-light);
        color: var(--primary-color);
        border: 1px solid var(--border-color);
        font-family: inherit;
        font-size: var(--fs-label);
        font-weight: 600;
        cursor: pointer;
        flex-shrink: 0;
        transition: background-color 0.2s, color 0.2s;
    }
    #equipmentModal .btn-apply-url:hover {
        background-color: var(--primary-color);
        color: #ffffff;
    }

    /* Preview: the whole photo on a blurred fill, actions always visible */
    #equipmentModal .image-preview-container {
        position: relative;
        width: 100%;
        height: auto;
        aspect-ratio: 16 / 9;
        border-radius: 14px;
        overflow: hidden;
        background-color: #f1f5f9;
        border: 1px solid var(--border-color);
        box-shadow: none;
        isolation: isolate;
    }
    .dark-theme #equipmentModal .image-preview-container {
        background-color: #0f172a;
    }
    #equipmentModal .image-preview-container .preview-backdrop {
        position: absolute;
        top: -32px;
        left: -32px;
        width: calc(100% + 64px);
        height: calc(100% + 64px);
        max-width: none;
        object-fit: cover;
        filter: blur(16px);
    }
    #equipmentModal .image-preview-container #imagePreviewImg {
        position: relative;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    #equipmentModal .preview-actions-overlay {
        position: absolute;
        top: auto;
        left: auto;
        bottom: 12px;
        right: 12px;
        padding: 0;
        background: none;
        display: flex;
        gap: 8px;
        opacity: 1;
        transform: none;
    }
    #equipmentModal .btn-preview-action {
        height: 32px;
        padding: 0 12px;
        border-radius: 8px;
        font-family: inherit;
        font-size: var(--fs-small);
        font-weight: 600;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
        transform: none;
        transition: background-color 0.2s;
    }
    #equipmentModal .btn-preview-action.replace {
        background-color: #ffffff;
        color: #1e293b;
    }
    #equipmentModal .btn-preview-action.replace:hover {
        background-color: #f1f5f9;
    }
    #equipmentModal .btn-preview-action.remove {
        background-color: #ef4444;
        color: #ffffff;
    }
    #equipmentModal .btn-preview-action.remove:hover {
        background-color: #dc2626;
    }

    @media (max-width: 640px) {
        #equipmentModal .form-row-flat {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 480px) {
        #equipmentModal {
            padding: 12px;
        }
        #equipmentModal .eq-modal-card {
            max-height: 94vh;
            border-radius: 18px;
        }
        #equipmentModal .eq-modal-header {
            padding: 18px 18px 14px;
        }
        #equipmentModal .eq-modal-body {
            padding: 16px 18px 20px;
        }
        #equipmentModal .eq-modal-footer {
            flex-wrap: wrap;
            padding: 12px 18px;
        }
        #equipmentModal .eq-modal-footnote {
            flex-basis: 100%;
        }
        #equipmentModal .eq-modal-footer .btn-submit-request,
        #equipmentModal .eq-modal-footer .btn-modal-secondary {
            flex: 1;
            min-width: 0;
        }
    }
</style>
@endpush

@section('content')
    <div class="equipment-container">
        {{-- Header section --}}
        <div class="equipment-header-section">
            <h2>Equipment Management</h2>
            <p>Manage and monitor all school equipment inventory</p>
        </div>

        {{-- Controls bar --}}
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchEquipment" placeholder="Search equipment...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterCategory">
                        <option value="all">All Categories</option>
                        @foreach ($dbCategories as $cat)
                            <option value="{{ $cat->category_id }}">{{ $cat->category_name }}</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div class="controls-right" style="display: flex; gap: 10px; align-items: center;">
                <button class="btn-add-category" id="addCategoryBtn" style="height: 46px; padding: 0 20px; border-radius: 23px; background-color: var(--card-bg, #ffffff); color: var(--text-main, #0f172a); border: 1px solid var(--border-color, #e2e8f0); font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <i class="fa-solid fa-folder-plus" style="color: #385585;"></i> Add Category
                </button>
                <button class="btn-add-equipment" id="addEquipmentBtn">
                    <i class="fa-solid fa-plus"></i> Add Equipment
                </button>
            </div>
        </div>

        {{-- Equipment cards grid --}}
        <div class="equipment-grid" id="equipmentGrid">
            {{-- Loaded dynamically via JavaScript --}}
        </div>
    </div>

    {{-- Equipment Modal (Add / Edit): same layout as the Department Equipment form --}}
    <div class="modal-overlay" id="equipmentModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalSubtitle">
        <div class="modal-card eq-modal-card">
            <header class="eq-modal-header">
                <div class="eq-modal-icon" aria-hidden="true"><i class="fa-solid fa-plus" id="modalIcon"></i></div>
                <div class="eq-modal-heading">
                    <h3 class="eq-modal-title" id="modalTitle">Add New Equipment</h3>
                    <p class="eq-modal-subtitle" id="modalSubtitle">Provide details for the inventory item</p>
                </div>
                <button type="button" class="modal-close" id="closeModalBtn" aria-label="Close">&times;</button>
            </header>

            <form id="equipmentForm" class="eq-modal-form">
                <div class="eq-modal-body">
                    <input type="hidden" id="editItemId" name="equipment_id" value="">
                    <input type="hidden" id="eqFormImg" name="image" value="">

                    <section class="eq-form-section" aria-labelledby="eqPhotoHeading">
                        <h4 class="eq-form-section-title" id="eqPhotoHeading">Equipment Photo <span class="req" aria-hidden="true">*</span></h4>
                        <div class="image-upload-wrapper" id="imageUploadWrapper">
                            {{-- Dropzone State: click or drag a file (the file input covers the zone) --}}
                            <div class="image-dropzone" id="imageDropzone">
                                <span class="dropzone-icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                <span class="dropzone-text">
                                    <span class="main-text">Drag and drop or <span class="browse-link">browse</span> to upload</span>
                                    <span class="sub-text">PNG, JPG, JPEG or WEBP, up to 5 MB</span>
                                </span>
                                <input type="file" id="imageFileInput" accept="image/*" class="file-input-hidden" aria-label="Upload equipment photo">
                            </div>

                            {{-- URL Input Alternative --}}
                            <div class="image-url-alternative" id="imageUrlAlternative">
                                <span class="or-separator">or use an image link</span>
                                <div class="url-input-container">
                                    {{-- type="text", not "url": a pasted /storage/... path would otherwise silently block the save --}}
                                    <input type="text" inputmode="url" autocomplete="off" id="eqFormImgUrl" class="form-control-flat" placeholder="https://example.com/photo.jpg" aria-label="Image URL">
                                    <button type="button" class="btn-apply-url" id="btnApplyUrl">Apply</button>
                                </div>
                            </div>

                            {{-- Image Preview State --}}
                            <div class="image-preview-container" id="imagePreviewContainer" style="display: none;">
                                <img src="" alt="" class="preview-backdrop" id="imagePreviewBackdrop" aria-hidden="true">
                                <img src="" alt="Selected equipment photo" id="imagePreviewImg">
                                <div class="preview-actions-overlay">
                                    <button type="button" class="btn-preview-action replace" id="btnReplacePreview">
                                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Change
                                    </button>
                                    <button type="button" class="btn-preview-action remove" id="btnRemovePreview">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqBasicHeading">
                        <h4 class="eq-form-section-title" id="eqBasicHeading">Basic Information</h4>
                        <div class="form-group-flat">
                            <label for="eqFormName">Equipment Name <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="eqFormName" name="name" class="form-control-flat" required placeholder="e.g., Dell Latitude 5420 Laptop">
                        </div>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormCategory">Category <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormCategory" name="category_id" class="form-control-flat" required>
                                        <option value="">Select Category</option>
                                        @foreach ($dbCategories as $cat)
                                            <option value="{{ $cat->category_id }}">{{ $cat->category_name }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormStatus">Status <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormStatus" name="status" class="form-control-flat" required>
                                        <option value="Available">Available</option>
                                        <option value="Unavailable">Unavailable</option>
                                        <option value="On Hold">On Hold</option>
                                        <option value="Under Maintenance">Under Maintenance</option>
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqIdentHeading">
                        <h4 class="eq-form-section-title" id="eqIdentHeading">Identification</h4>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormBrand">Brand <span class="req" aria-hidden="true">*</span></label>
                                <input type="text" id="eqFormBrand" name="brand" class="form-control-flat" required placeholder="e.g., Dell">
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormModel">Model</label>
                                <input type="text" id="eqFormModel" name="model" class="form-control-flat" placeholder="e.g., Latitude 5420">
                            </div>
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormSerial">Serial Number <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="eqFormSerial" name="serial_number" class="form-control-flat mono" required placeholder="e.g., SN-DELL-98765" aria-describedby="eqSerialHint">
                            <p class="form-hint" id="eqSerialHint">Must be unique across all equipment.</p>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqAssignHeading">
                        <h4 class="eq-form-section-title" id="eqAssignHeading">Assignment &amp; Approval</h4>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormDepartment">Department <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormDepartment" name="department_id" class="form-control-flat" required>
                                        <option value="">Select Department</option>
                                        @foreach ($dbDepartments as $dept)
                                            <option value="{{ $dept->department_id }}">{{ $dept->department_name }}{{ $dept->department_code ? ' ('.$dept->department_code.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormClassification">Classification <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormClassification" name="classification" class="form-control-flat" required aria-describedby="eqClassHint">
                                        <option value="">Select Classification</option>
                                        <option value="Expensive">Expensive</option>
                                        <option value="Not Expensive">Not Expensive</option>
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                        <p class="form-hint" id="eqClassHint">Requests for Expensive equipment need approval from both the admin and the department.</p>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqStockHeading">
                        <h4 class="eq-form-section-title" id="eqStockHeading">Inventory</h4>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormAvail">Available Quantity <span class="req" aria-hidden="true">*</span></label>
                                <input type="number" id="eqFormAvail" name="available_qty" class="form-control-flat" min="0" required value="1" inputmode="numeric" aria-describedby="eqQtyHint">
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormTotal">Total Quantity <span class="req" aria-hidden="true">*</span></label>
                                <input type="number" id="eqFormTotal" name="total_qty" class="form-control-flat" min="0" required value="1" inputmode="numeric" aria-describedby="eqQtyHint">
                            </div>
                        </div>
                        <p class="form-hint" id="eqQtyHint">Available units can't be more than the total.</p>
                        <div class="form-group-flat">
                            <label for="eqFormAccessories">Accessories Included</label>
                            <input type="text" id="eqFormAccessories" name="accessories_included" class="form-control-flat" placeholder="e.g., Charger, Power Adapter, Carrying Case">
                        </div>
                    </section>
                </div>

                <footer class="eq-modal-footer">
                    <p class="eq-modal-footnote"><span class="req" aria-hidden="true">*</span> Required</p>
                    <button type="button" class="btn-modal-secondary" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn-submit-request" id="submitBtn">Add Item</button>
                </footer>
            </form>
        </div>
    </div>

    {{-- Category Modal (Add Category) --}}
    <div class="modal-overlay" id="categoryModal" style="display: none;">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Create a new equipment classification</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeCategoryModalBtn">&times;</button>
                <h3 class="modal-title-center">Add New Category</h3>

                <form id="categoryForm" class="new-modal-form" style="padding-top: 10px;">
                    <div class="form-group-flat">
                        <label for="catFormName">Category Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="catFormName" name="category_name" class="form-control-flat" required placeholder="e.g., Laboratory Equipment">
                    </div>

                    <div class="modal-actions-footer" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                        <button type="button" class="btn-modal-close" id="cancelCategoryBtn" style="height: 42px; padding: 0 18px; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0); background: transparent; color: var(--text-main); font-weight: 600; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn-submit-request" id="saveCategoryBtn" style="height: 42px; width: auto; padding: 0 24px;">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_DATA = @json(route('admin.equipment.data'));
        const URL_SAVE = @json(route('admin.equipment.save'));
        const URL_CATEGORY = @json(route('admin.equipment.category'));
        const URL_DELETE = @json(route('admin.equipment.delete'));

        // Master Equipment Array loaded from database
        let equipment = [];
        let dbCategories = @json($dbCategories);
        let dbDepartments = @json($dbDepartments);

        // DOM Elements
        const searchInput = document.getElementById('searchEquipment');
        const filterSelect = document.getElementById('filterCategory');
        const grid = document.getElementById('equipmentGrid');

        // Equipment Modal
        const modal = document.getElementById('equipmentModal');
        const addBtn = document.getElementById('addEquipmentBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const form = document.getElementById('equipmentForm');

        const modalTitle = document.getElementById('modalTitle');
        const modalSubtitle = document.getElementById('modalSubtitle');
        const modalIcon = document.getElementById('modalIcon');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const submitBtn = document.getElementById('submitBtn');
        const editItemIdInput = document.getElementById('editItemId');

        // Category Modal Elements
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const categoryModal = document.getElementById('categoryModal');
        const closeCategoryModalBtn = document.getElementById('closeCategoryModalBtn');
        const cancelCategoryBtn = document.getElementById('cancelCategoryBtn');
        const categoryForm = document.getElementById('categoryForm');
        const catFormNameInput = document.getElementById('catFormName');

        // Form Fields
        const eqNameField = document.getElementById('eqFormName');
        const eqBrandField = document.getElementById('eqFormBrand');
        const eqModelField = document.getElementById('eqFormModel');
        const eqSerialField = document.getElementById('eqFormSerial');
        const eqCategoryField = document.getElementById('eqFormCategory');
        const eqDepartmentField = document.getElementById('eqFormDepartment');
        const eqImgHiddenField = document.getElementById('eqFormImg');
        const eqImgUrlField = document.getElementById('eqFormImgUrl');
        const eqAvailField = document.getElementById('eqFormAvail');
        const eqTotalField = document.getElementById('eqFormTotal');
        const eqStatusField = document.getElementById('eqFormStatus');
        const eqClassificationField = document.getElementById('eqFormClassification');
        const eqAccessoriesField = document.getElementById('eqFormAccessories');

        // Uploader Elements
        const imageDropzone = document.getElementById('imageDropzone');
        const imageFileInput = document.getElementById('imageFileInput');
        const imageUrlAlternative = document.getElementById('imageUrlAlternative');
        const btnApplyUrl = document.getElementById('btnApplyUrl');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreviewImg = document.getElementById('imagePreviewImg');
        const imagePreviewBackdrop = document.getElementById('imagePreviewBackdrop');
        const btnReplacePreview = document.getElementById('btnReplacePreview');
        const btnRemovePreview = document.getElementById('btnRemovePreview');

        // Hidden image field (declared inside the form)
        const eqImgHidden = eqImgHiddenField;

        // Fetch Equipment from Database
        function loadEquipment() {
            fetch(URL_DATA)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        equipment = data.equipment || [];
                        renderEquipment();
                        filterEquipment();
                    }
                })
                .catch(err => console.error('Error loading equipment:', err));
        }

        // Render equipment cards dynamically
        function renderEquipment() {
            grid.innerHTML = '';
            if (!equipment || equipment.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state-container" style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 48px 24px; background: var(--bg-card); border-radius: 12px; border: 1px dashed var(--border-color);">
                        <i class="fa-solid fa-boxes-stacked" style="font-size: 32px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                        <h4 style="font-size: 16px; font-weight: 600; color: var(--text-main); margin-bottom: 4px;">No Equipment Registered</h4>
                        <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Click "Add Equipment" above to populate the equipment inventory.</p>
                    </div>
                `;
                return;
            }
            equipment.forEach(item => {
                const card = document.createElement('div');
                card.className = 'eq-card-admin';
                card.setAttribute('data-category-id', item.category_id || '');
                card.setAttribute('data-id', item.equipment_id);

                let statusClass = 'status-available';
                if (item.status === 'Unavailable') statusClass = 'status-unavailable';
                else if (item.status === 'On Hold') statusClass = 'status-on-hold';
                else if (item.status === 'Under Maintenance') statusClass = 'status-under-maintenance';

                const brandModel = (item.brand || '') + (item.model ? ' ' + item.model : '');
                const categoryName = item.category_name || 'Unassigned';
                const departmentName = item.department_name || 'Unassigned';
                const classification = item.classification || 'Not Expensive';
                const available = parseInt(item.available_qty) || 0;
                const total = parseInt(item.total_qty) || 0;
                const pct = total > 0 ? Math.min(100, Math.round((available / total) * 100)) : 0;
                const stockClass = available === 0 ? 'is-empty' : (available <= Math.max(1, Math.floor(total * 0.25)) ? 'is-low' : '');
                const imgUrl = item.image ? escapeHTML(item.image) : '';

                // Same card design as Department Equipment: photo, name + status,
                // brand/model, details list, availability bar, actions.
                card.innerHTML = `
                    <div class="eq-card-img-wrapper${imgUrl ? '' : ' is-missing'}">
                        ${imgUrl ? `
                        <img class="eq-card-img-backdrop" src="${imgUrl}" alt="" aria-hidden="true" loading="lazy" decoding="async">
                        <img class="eq-card-img" src="${imgUrl}" alt="${escapeHTML(item.name)}" loading="lazy" decoding="async"
                             onerror="this.onerror=null; this.parentElement.classList.add('is-missing');">` : ''}
                        <div class="eq-card-img-placeholder">
                            <i class="fa-regular fa-image" aria-hidden="true"></i>
                            <span>No image available</span>
                        </div>
                    </div>
                    <div class="eq-card-details">
                        <div class="eq-card-heading">
                            <h4 class="eq-card-title" title="${escapeHTML(item.name)}">${escapeHTML(item.name)}</h4>
                            <span class="eq-status-badge ${statusClass}">${escapeHTML(item.status)}</span>
                        </div>
                        ${brandModel ? `<p class="eq-card-subtitle" title="${escapeHTML(brandModel)}">${escapeHTML(brandModel)}</p>` : ''}

                        <dl class="eq-card-specs">
                            <div class="eq-spec-row">
                                <dt>Serial No.</dt>
                                <dd class="mono">${escapeHTML(item.serial_number || '—')}</dd>
                            </div>
                            <div class="eq-spec-row">
                                <dt>Category</dt>
                                <dd class="cat-val">${escapeHTML(categoryName)}</dd>
                            </div>
                            <div class="eq-spec-row">
                                <dt>Department</dt>
                                <dd>${escapeHTML(departmentName)}</dd>
                            </div>
                            <div class="eq-spec-row">
                                <dt>Classification</dt>
                                <dd><span class="eq-class-badge ${classification === 'Expensive' ? 'class-expensive' : 'class-not-expensive'}">${escapeHTML(classification)}</span></dd>
                            </div>
                            ${item.accessories_included ? `
                            <div class="eq-spec-row">
                                <dt>Accessories</dt>
                                <dd>${escapeHTML(item.accessories_included)}</dd>
                            </div>` : ''}
                        </dl>

                        <div class="eq-stock ${stockClass}">
                            <div class="eq-stock-text">
                                <span><strong>${escapeHTML(String(item.available_qty))}</strong> / ${escapeHTML(String(item.total_qty))} available</span>
                                ${available === 0 ? '<span class="eq-stock-flag">None left</span>' : ''}
                            </div>
                            <div class="eq-stock-bar" role="progressbar" aria-label="Available units" aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${available}">
                                <span style="width: ${pct}%"></span>
                            </div>
                        </div>
                    </div>
                    <div class="eq-card-actions">
                        <button type="button" class="btn-card-edit" onclick="openEditModal(${item.equipment_id})"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Edit</button>
                        <button type="button" class="btn-card-delete" onclick="deleteEquipment(${item.equipment_id})"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function escapeHTML(str) {
            if (!str) return '';
            return String(str).replace(/[&<>'"]/g,
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        // Helper to show preview state
        function showPreview(url) {
            imagePreviewImg.src = url;
            if (imagePreviewBackdrop) imagePreviewBackdrop.src = url;
            eqImgHidden.value = url;
            imageDropzone.style.display = 'none';
            imageUrlAlternative.style.display = 'none';
            imagePreviewContainer.style.display = 'block';
        }

        // Helper to reset back to empty upload state
        function resetUploader() {
            imagePreviewImg.src = "";
            if (imagePreviewBackdrop) imagePreviewBackdrop.src = "";
            imageFileInput.value = "";
            eqImgHidden.value = "";
            if (eqImgUrlField) eqImgUrlField.value = "";
            imageDropzone.style.display = 'flex';
            imageUrlAlternative.style.display = 'flex';
            imagePreviewContainer.style.display = 'none';
        }

        function handleFile(file) {
            if (file && file.type.startsWith('image/')) {
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('File Too Large', 'Please upload an image smaller than 5MB.', 'error');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    showPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                showNotification('Invalid File', 'Please select a valid image file.', 'error');
            }
        }

        imageFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            handleFile(file);
        });

        // Drag and drop event listeners
        ['dragenter', 'dragover'].forEach(eventName => {
            imageDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                imageDropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            imageDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                imageDropzone.classList.remove('dragover');
            }, false);
        });

        imageDropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            handleFile(file);
        }, false);

        btnApplyUrl.addEventListener('click', (e) => {
            e.preventDefault();
            const urlValue = eqImgUrlField.value.trim();
            if (urlValue) {
                showPreview(urlValue);
            } else {
                showNotification('Empty URL', 'Please paste a valid image URL.', 'error');
            }
        });

        if (eqImgUrlField) {
            eqImgUrlField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    btnApplyUrl.click();
                }
            });
        }

        btnReplacePreview.addEventListener('click', () => {
            imageFileInput.click();
        });

        btnRemovePreview.addEventListener('click', () => {
            resetUploader();
        });

        // Open / Close Category Modal Logic
        function openCategoryModal() {
            categoryForm.reset();
            categoryModal.style.display = 'flex';
            setTimeout(() => {
                categoryModal.classList.add('show');
                if (catFormNameInput) catFormNameInput.focus();
            }, 10);
        }

        function closeCategoryModal() {
            categoryModal.classList.remove('show');
            setTimeout(() => {
                categoryModal.style.display = 'none';
                categoryForm.reset();
            }, 200);
        }

        if (addCategoryBtn) addCategoryBtn.addEventListener('click', openCategoryModal);
        if (closeCategoryModalBtn) closeCategoryModalBtn.addEventListener('click', closeCategoryModal);
        if (cancelCategoryBtn) cancelCategoryBtn.addEventListener('click', closeCategoryModal);
        if (categoryModal) {
            categoryModal.addEventListener('click', (e) => {
                if (e.target === categoryModal) closeCategoryModal();
            });
        }

        // Add Category Submit
        if (categoryForm) {
            categoryForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const catName = catFormNameInput.value.trim();
                if (!catName) {
                    showNotification('Validation Error', 'Please enter a category name.', 'error');
                    return;
                }

                const formData = new FormData(categoryForm);
                formData.append('_token', CSRF_TOKEN);
                fetch(URL_CATEGORY, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const newCat = data.category;
                        dbCategories.push(newCat);

                        // Add to Category Filter
                        const filterOpt = document.createElement('option');
                        filterOpt.value = newCat.category_id;
                        filterOpt.textContent = newCat.category_name;
                        filterSelect.appendChild(filterOpt);

                        // Add to Form Select
                        const formOpt = document.createElement('option');
                        formOpt.value = newCat.category_id;
                        formOpt.textContent = newCat.category_name;
                        eqCategoryField.appendChild(formOpt);
                        eqCategoryField.value = newCat.category_id;

                        closeCategoryModal();
                        showNotification('Category Added', `Category "${newCat.category_name}" saved to database successfully!`, 'success');
                    } else {
                        showNotification('Error', data.message || 'Failed to save category.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error saving category:', err);
                    showNotification('System Error', 'An unexpected error occurred while saving the category.', 'error');
                });
            });
        }

        // Search & Filter Trigger
        function filterEquipment() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedCatId = filterSelect.value;
            const cards = grid.querySelectorAll('.eq-card-admin');

            cards.forEach(card => {
                const title = card.querySelector('.eq-card-title').textContent.toLowerCase();
                const cardCatId = card.getAttribute('data-category-id');

                const matchesSearch = title.includes(query);
                const matchesCategory = (selectedCatId === 'all' || cardCatId === selectedCatId);

                if (matchesSearch && matchesCategory) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterEquipment);
        filterSelect.addEventListener('change', filterEquipment);

        // Open Add Modal
        addBtn.addEventListener('click', () => {
            form.reset();
            editItemIdInput.value = "";
            modalTitle.textContent = "Add New Equipment";
            modalSubtitle.textContent = "Provide details for the inventory item";
            if (modalIcon) modalIcon.className = 'fa-solid fa-plus';
            submitBtn.textContent = "Add Item";

            resetUploader();
            scrollFormToTop();
            modal.classList.add('show');
        });

        // Close Modal
        closeModalBtn.addEventListener('click', closeModal);
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Each time the form opens it starts at the photo, not where it was left
        function scrollFormToTop() {
            const body = form.querySelector('.eq-modal-body');
            if (body) body.scrollTop = 0;
        }

        function closeModal() {
            modal.classList.remove('show');
            form.reset();
            resetUploader();
        }

        // Open Edit Modal
        window.openEditModal = function(id) {
            const item = equipment.find(e => e.equipment_id == id);
            if (!item) return;

            editItemIdInput.value = item.equipment_id;
            eqNameField.value = item.name || '';
            eqBrandField.value = item.brand || '';
            eqModelField.value = item.model || '';
            eqSerialField.value = item.serial_number || '';
            eqCategoryField.value = item.category_id || '';
            eqDepartmentField.value = item.department_id || '';
            eqAvailField.value = item.available_qty !== undefined ? item.available_qty : 0;
            eqTotalField.value = item.total_qty !== undefined ? item.total_qty : 0;
            eqStatusField.value = item.status || 'Available';
            eqClassificationField.value = item.classification === 'Expensive' ? 'Expensive' : 'Not Expensive';
            eqAccessoriesField.value = item.accessories_included || '';

            if (item.image) {
                showPreview(item.image);
            } else {
                resetUploader();
            }

            modalTitle.textContent = "Edit Equipment";
            modalSubtitle.textContent = "Update inventory details for this item";
            if (modalIcon) modalIcon.className = 'fa-solid fa-pen-to-square';
            submitBtn.textContent = "Save Changes";

            scrollFormToTop();
            modal.classList.add('show');
        };

        // Delete Equipment
        window.deleteEquipment = function(id) {
            const item = equipment.find(e => e.equipment_id == id);
            if (!item) return;

            if (confirm(`Are you sure you want to delete "${item.name}" (${item.serial_number})?`)) {
                const formData = new FormData();
                formData.append('_token', CSRF_TOKEN);
                formData.append('equipment_id', id);

                fetch(URL_DELETE, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Deleted Successfully', data.message, 'success');
                        loadEquipment();
                    } else {
                        showNotification('Error', data.message || 'Failed to delete equipment.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error deleting equipment:', err);
                    showNotification('System Error', 'An error occurred while deleting equipment.', 'error');
                });
            }
        };

        // Form Submit (Add / Edit)
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const imgVal = eqImgHidden.value.trim();
            if (!imgVal) {
                showNotification('Image Required', 'Please upload an image or enter an image URL.', 'error');
                return;
            }

            const availQty = parseInt(eqAvailField.value) || 0;
            const totalQty = parseInt(eqTotalField.value) || 0;

            if (availQty > totalQty) {
                showNotification('Validation Error', 'Available Quantity cannot exceed Total Quantity.', 'error');
                return;
            }

            if (!['Expensive', 'Not Expensive'].includes(eqClassificationField.value)) {
                showNotification('Validation Error', 'Please select an equipment classification.', 'error');
                return;
            }

            const formData = new FormData(form);
            formData.append('_token', CSRF_TOKEN);
            fetch(URL_SAVE, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    showNotification('Success', data.message, 'success');
                    loadEquipment();
                } else {
                    showNotification('Validation Error', data.message || 'Failed to save equipment.', 'error');
                }
            })
            .catch(err => {
                console.error('Error saving equipment:', err);
                showNotification('System Error', 'An unexpected error occurred while saving equipment.', 'error');
            });
        });

        // Initial rendering
        loadEquipment();

        // Borrow request approvals (admin or department) change the available
        // quantity in the background, so refresh when this tab regains focus.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                loadEquipment();
            }
        });
    });
</script>
@endpush
