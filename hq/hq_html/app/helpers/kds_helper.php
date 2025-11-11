<?php
// [A1 UTC SYNC] 引入新的时间助手
require_once __DIR__ . '/datetime_helper.php';

require_once __DIR__ . '/kds/kds_repo_a.php';
require_once __DIR__ . '/kds/kds_repo_b.php';
require_once __DIR__ . '/kds/kds_repo_c.php';

require_once __DIR__ . '/kds/kds_sop_engine.php';

/**
 * Toptea HQ - cpsys
 * KDS Data Helper Functions (Bootstrapper)
 * Engineer: Gemini | Date: 2025-11-05 | Revision: 17.0 (Helper Cleanup)
 *
 * [GEMINI V17.0 REFACTOR]:
 * 1. Removed all duplicate function definitions (KdsSopParser, id_by_code, get_product, etc.)
 * that were already present in the required 'kds_repo_*.php' or 'kds_sop_engine.php' files.
 * 2. This file now acts as a clean bootstrapper for the kds helper library.
 * 3. Removed require_once for kds_services.php as its only function (norm_cat)
 * was merged into kds_repo_b.php.
 */

// === 新增：把缺失的小型通用函数集中补齐（最小侵入修复） ===
require_once __DIR__ . '/kds/kds_repo_fix.php';