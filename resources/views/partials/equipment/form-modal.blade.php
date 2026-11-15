{{-- Add / Edit Equipment window, shared by Admin Equipment and Department
     Equipment. Its behaviour (photo uploader, checks, saving) is in
     public/js/equipment-form.js; each page keeps its own styles.

     $categories   categories (category_id, category_name) for the Category list
     $departments  optional: when given (Admin), a Department list is shown;
                   Department accounts always save to their own department
     $classHint    the hint under Classification
     $classLockedHint  optional: shown on edits when the page locks the
                   classification (Department: only the admin may change it)

     Field names match what both servers' save actions read. --}}
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
                {{-- The photo that is saved: an uploaded file (as data) or an image link --}}
                <input type="hidden" id="eqFormImg" name="image" value="">

                <section class="eq-form-section" aria-labelledby="eqPhotoHeading">
                    <h4 class="eq-form-section-title" id="eqPhotoHeading">Equipment Photo <span class="req" aria-hidden="true">*</span></h4>
                    <div class="image-upload-wrapper" id="imageUploadWrapper">
                        {{-- Drop zone: click, keyboard (Enter / Space) or drag a file --}}
                        <label class="image-dropzone" id="imageDropzone" for="imageFileInput" tabindex="0">
                            <span class="dropzone-icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                            <span class="dropzone-text">
                                <span class="main-text">Drag and drop or <span class="browse-link">browse</span> to upload</span>
                                <span class="sub-text">PNG, JPG, JPEG or WEBP, up to 5 MB</span>
                            </span>
                        </label>
                        {{-- `hidden`: never drawn on either page; the drop zone opens it --}}
                        <input type="file" id="imageFileInput" accept="image/*" class="file-input-hidden" hidden aria-label="Upload equipment photo">

                        {{-- Image link instead of a file --}}
                        <div class="image-url-alternative" id="imageUrlAlternative">
                            <span class="or-separator">or use an image link</span>
                            <div class="url-input-container">
                                {{-- type="text", not "url": stored photos are paths like /storage/images/…, which a url field rejects and silently blocks the save --}}
                                <input type="text" inputmode="url" autocomplete="off" id="eqFormImgUrl" class="form-control-flat" placeholder="https://example.com/photo.jpg" aria-label="Image URL">
                                <button type="button" class="btn-apply-url" id="btnApplyUrl">Apply</button>
                            </div>
                        </div>

                        {{-- Preview of the chosen photo --}}
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
                        <input type="text" id="eqFormName" name="name" class="form-control-flat" required maxlength="100" placeholder="e.g. Dell Latitude 5420 Laptop">
                    </div>
                    <div class="form-row-flat">
                        <div class="form-group-flat">
                            <label for="eqFormCategory">Category <span class="req" aria-hidden="true">*</span></label>
                            <div class="select-wrapper">
                                <select id="eqFormCategory" name="category_id" class="form-control-flat" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ data_get($cat, 'category_id') }}">{{ data_get($cat, 'category_name') }}</option>
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
                                    <option value="Retired">Retired (permanently out of use)</option>
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
                            <input type="text" id="eqFormBrand" name="brand" class="form-control-flat" required maxlength="50" placeholder="e.g. Dell">
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormModel">Model</label>
                            <input type="text" id="eqFormModel" name="model" class="form-control-flat" maxlength="50" placeholder="e.g. Latitude 5420">
                        </div>
                    </div>
                    <div class="form-group-flat">
                        <label for="eqFormSerial">Serial Number <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" id="eqFormSerial" name="serial_number" class="form-control-flat mono" required maxlength="50" placeholder="e.g. SN-DELL-98765" aria-describedby="eqSerialHint">
                        <p class="form-hint" id="eqSerialHint">Must be unique across all equipment.</p>
                    </div>
                </section>

                @isset($departments)
                    {{-- Admin: choose the department and the classification side by side --}}
                    <section class="eq-form-section" aria-labelledby="eqApprovalHeading">
                        <h4 class="eq-form-section-title" id="eqApprovalHeading">Assignment &amp; Approval</h4>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormDepartment">Department <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormDepartment" name="department_id" class="form-control-flat" required>
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->department_id }}">{{ $dept->department_name }}{{ $dept->department_code ? ' ('.$dept->department_code.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="form-group-flat">
                                @include('partials.equipment.classification-field')
                            </div>
                        </div>
                        <p class="form-hint" id="eqClassHint">{{ $classHint }}</p>
                    </section>
                @else
                    {{-- Department: the department is always the account's own --}}
                    <section class="eq-form-section" aria-labelledby="eqApprovalHeading">
                        <h4 class="eq-form-section-title" id="eqApprovalHeading">Approval</h4>
                        <div class="form-group-flat">
                            @include('partials.equipment.classification-field')
                            <p class="form-hint" id="eqClassHint">{{ $classHint }}</p>
                            @isset($classLockedHint)
                                <p class="form-hint" id="eqClassLockedHint" hidden>{{ $classLockedHint }}</p>
                            @endisset
                        </div>
                    </section>
                @endisset

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
                    <p class="form-hint" id="eqQtyHint" aria-live="polite">Available units can't be more than the total.</p>
                    <div class="form-group-flat">
                        <label for="eqFormAccessories">Accessories Included</label>
                        <input type="text" id="eqFormAccessories" name="accessories_included" class="form-control-flat" maxlength="150" placeholder="e.g. Charger, carrying case">
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
