<?php
/**
* Toptea HQ - Product Availability View (L1+L3)
* Engineer: Gemini | Date: 2025-11-08
* Revision: 2.6 (Style Fix: Force Primary Blue Button per user 3.png)
*/
?>

<div class="card">
<div class="card-header">
<div class="d-flex justify-content-between align-items-center">
<h5 class="mb-0">产品物料清单与上架管理</h5>
<div class="d-flex gap-2 align-items-center" style="min-width: 50%;">
<button class="btn text-white text-nowrap" id="btn-show-material-usage" type="button" data-bs-toggle="modal" data-bs-target="#material-usage-modal"
style="background-color: #0d6efd; border-color: #0d6efd; padding-left: 1.5rem; padding-right: 1.5rem;">
<i class="bi bi-card-list me-2"></i>物料使用总览
</button>
<select class="form-select flex-grow-1" id="material-search-select">
<option value="">-- 按物料搜索 --</option>
<?php foreach ($material_options as $material): ?>
<option value="<?php echo $material['id']; ?>">
[<?php echo htmlspecialchars($material['material_code']); ?>] <?php echo htmlspecialchars($material['name_zh']); ?>
</option>
<?php endforeach; ?>
</select>
<button class="btn btn-outline-light flex-shrink-0" id="clear-search-btn" type="button"><i class="bi bi-x-lg"></i></button>
</div>
</div>
</div>
<div class="card-body">
<div class="alert alert-info">
<i class="bi bi-info-circle-fill me-2"></i>
此页面显示所有 POS 产品及其关联的核心物料 (L1 基础配方 + L3 特例配方)。
可以通过物料搜索，批量“上架”或“下架”使用该物料的产品。
</div>

<div id="product-list-container" class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-3">
<div class="col-12 text-center p-5">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Loading...</span>
</div>
</div>
</div>
</div>
</div>

<template id="product-card-template">
<div class="col product-card-wrapper">
<div class="card h-100">
<div class="card-body d-flex">
<div class="flex-grow-1">
<h6 class="card-title product-name mb-1">Product Name</h6>
<small class="text-muted product-id d-none">ID: 000</small>
<ul class="list-unstyled mt-2 mb-0 product-materials-list">
<li><span class="badge text-bg-success">物料1</span></li>
<li><span class="badge text-bg-success">物料2</span></li>
</ul>
</div>
<div class="flex-shrink-0 ms-3">
<div class="form-check form-switch form-switch-lg">
<input class="form-check-input product-active-toggle" type="checkbox" role="switch">
<label class="form-check-label product-status-label">已上架</label>
</div>
</div>
</div>
</div>
</div>
</template>

<div class="modal fade" id="material-usage-modal" tabindex="-1" aria-labelledby="materialUsageModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="materialUsageModalLabel">物料使用总览</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<p class="text-muted">此列表显示所有物料（未删除）在 POS 产品配方中的使用情况，帮助您核对库存。</p>
<div class="nav nav-tabs" id="material-usage-tabs" role="tablist">
<button class="nav-link active" id="tab-on-sale-btn" data-bs-toggle="tab" data-bs-target="#tab-on-sale" type="button" role="tab" aria-controls="tab-on-sale" aria-selected="true">
在售产品使用中 (<span id="count-on-sale">0</span>)
</button>
<button class="nav-link" id="tab-off-sale-btn" data-bs-toggle="tab" data-bs-target="#tab-off-sale" type="button" role="tab" aria-controls="tab-off-sale" aria-selected="false">
已下架产品使用 (<span id="count-off-sale">0</span>)
</button>
<button class="nav-link" id="tab-unused-btn" data-bs-toggle="tab" data-bs-target="#tab-unused" type="button" role="tab" aria-controls="tab-unused" aria-selected="false">
未关联产品 (<span id="count-unused">0</span>)
</button>
</div>
<div class="tab-content border border-top-0 rounded-bottom p-3" id="material-usage-tabs-content">
<div class="tab-pane fade show active" id="tab-on-sale" role="tabpanel">
<div class="list-group" id="list-on-sale"></div>
</div>
<div class="tab-pane fade" id="tab-off-sale" role="tabpanel">
<div class="list-group" id="list-off-sale"></div>
</div>
<div class="tab-pane fade" id="tab-unused" role="tabpanel">
<div class="list-group" id="list-unused"></div>
</div>
<div id="material-usage-loading" class="text-center p-5" style="display: none;">
<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
</div>
</div>
</div>
</div>