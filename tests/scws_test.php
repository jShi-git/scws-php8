<?php
/**
 * SCWS 分词测试页
 * 直接访问: /scws_test.php?text=测试分词&multi=1&ignore=1&duality=1
 */
$GLOBALS['_beginTime'] = microtime(true);
ini_set('display_errors', '1');
error_reporting(E_ALL);

//需要放置词典文件到对应目录
define('WORK_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);

header('Content-Type: text/html; charset=utf-8');

// 兼容：如果扩展没有定义这些常量（或走纯 PHP 回退），这里补齐
if (!defined('SCWS_XDICT_XDB')) {
	define('SCWS_XDICT_XDB', 1);
	define('SCWS_XDICT_MEM', 2);
	define('SCWS_XDICT_TXT', 4);
}

function _bool_from_req($v, $default = false) {
	if ($v === null) return $default;
	if ($v === true || $v === false) return $v;
	$v = strtolower(trim((string)$v));
	return in_array($v, array('1', 'true', 'yes', 'on'), true);
}

function _h($s) {
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$text = isset($_REQUEST['text']) ? (string)$_REQUEST['text'] : '这是一个测试文本，用于验证 scws 扩展分词是否正常。党课PPT 雷锋精神 组织生活会 2026';
$charset = isset($_REQUEST['charset']) ? strtolower(trim((string)$_REQUEST['charset'])) : 'utf8';
if (!in_array($charset, array('utf8', 'gbk', 'big5'), true)) {
	$charset = 'utf8';
}
$multi = isset($_REQUEST['multi']) ? (int)$_REQUEST['multi'] : 1;
if ($multi < 0) $multi = 0;
if ($multi > 15) $multi = 15;
$ignore = _bool_from_req($_REQUEST['ignore'] ?? null, true);
$duality = _bool_from_req($_REQUEST['duality'] ?? null, true);
$topn = isset($_REQUEST['topn']) ? (int)$_REQUEST['topn'] : 10;
if ($topn < 0) $topn = 0;
if ($topn > 50) $topn = 50;

$extLoaded = extension_loaded('scws');
$extVersion = $extLoaded ? phpversion('scws') : '';
$engine = $extLoaded ? 'SCWS 扩展' : 'PSCWS4（纯 PHP 回退）';

$errors = array();
$loadedFiles = array();
$wordsArray = array();
$tops = null;

try {

	$so = scws_new();
	if (!is_object($so)) {
		throw new Exception('scws_new() 返回值不是对象');
	}

	// 基本参数
	@$so->set_charset($charset);

	// 规则文件
	$ruleFile = WORK_PATH . 'conf/dicts/rules.utf8.ini';
	if (is_file($ruleFile)) {
		@$so->set_rule($ruleFile);
		$loadedFiles[] = array('type' => 'rule', 'path' => $ruleFile);
	} else {
		$errors[] = "规则文件不存在：{$ruleFile}";
	}

	// 词典文件（项目仓库里可能没提交 .xdb，故这里做存在性判断 + 自动降级）
	$dictCandidates = array(
		array('path' => WORK_PATH . 'conf/dicts/dict.utf8.xdb', 'mode' => SCWS_XDICT_XDB),
		array('path' => WORK_PATH . 'conf/dicts/dict_user.txt', 'mode' => SCWS_XDICT_TXT),
	);
	$loadedDictCount = 0;
	foreach ($dictCandidates as $d) {
		$f = $d['path'];
		if (!is_file($f)) {
			continue;
		}
		if (($d['mode'] ?? SCWS_XDICT_XDB) === SCWS_XDICT_XDB) {
			// XDB：扩展/纯PHP都支持这个签名（纯PHP里会尝试查找同名 txt）
			@$so->add_dict($f);
		} else {
			@$so->add_dict($f, $d['mode']);
		}
		$loadedFiles[] = array('type' => 'dict', 'path' => $f, 'mode' => (int)$d['mode']);
		$loadedDictCount++;
	}
	if ($loadedDictCount === 0) {
		$errors[] = '未加载到任何词典文件（conf/dicts/ 下未找到可用词典），分词效果可能不佳或为空。';
	}

	@$so->set_ignore($ignore);
	@$so->set_multi($multi);
	@$so->set_duality($duality);

	@$so->send_text($text);
	while ($res = @$so->get_result()) {
		if (is_array($res)) {
			$wordsArray = array_merge($wordsArray, $res);
		} else {
			break;
		}
	}

	// tops（可选）
	if ($topn > 0 && method_exists($so, 'get_tops')) {
		$tops = @$so->get_tops($topn);
		if ($tops === null || $tops === false) {
			$tops = @$so->get_tops($topn, '');
		}
	}

	if (method_exists($so, 'close')) {
		@$so->close();
	}
} catch (Throwable $e) {
	$errors[] = $e->getMessage();
}

// 输出
$wordList = array();
foreach ($wordsArray as $w) {
	if (isset($w['word']) && $w['word'] !== '') {
		$wordList[] = (string)$w['word'];
	}
}
$uniqWords = array_values(array_unique($wordList));

?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>SCWS 分词测试</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif; margin: 20px; background: #f6f7fb; color: #111827; }
		.container { max-width: 1200px; margin: 0 auto; }
		.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin: 12px 0; }
		h1 { font-size: 20px; margin: 0 0 8px; }
		h2 { font-size: 16px; margin: 0 0 10px; }
		.row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
		input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
		select, input[type="number"] { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; }
		label { display: inline-flex; gap: 6px; align-items: center; }
		button { padding: 10px 14px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; cursor: pointer; }
		button:hover { background: #1d4ed8; }
		.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #eef2ff; color: #3730a3; }
		.badge.ok { background: #dcfce7; color: #166534; }
		.badge.bad { background: #fee2e2; color: #991b1b; }
		.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
		table { width: 100%; border-collapse: collapse; }
		th, td { border-bottom: 1px solid #eee; padding: 8px 10px; text-align: left; font-size: 13px; }
		th { background: #f9fafb; position: sticky; top: 0; }
		.small { color: #6b7280; font-size: 12px; }
		.warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 10px; border-radius: 8px; }
		.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px; border-radius: 8px; }
		.pills { display:flex; flex-wrap: wrap; gap: 6px; }
		.pill { background:#f3f4f6; border:1px solid #e5e7eb; padding:4px 8px; border-radius:999px; font-size:12px; }
	</style>
</head>
<body>
<div class="container">
	<div class="card">
		<h1>SCWS 分词测试页</h1>
		<div class="row small">
			<div>PHP: <span class="mono"><?php echo _h(PHP_VERSION); ?></span></div>
			<div>引擎: <span class="badge <?php echo $extLoaded ? 'ok' : 'bad'; ?>"><?php echo _h($engine); ?></span></div>
			<div>scws 扩展: <span class="mono"><?php echo $extLoaded ? 'loaded' : 'not loaded'; ?></span></div>
			<?php if ($extLoaded): ?>
				<div>扩展版本: <span class="mono"><?php echo _h($extVersion); ?></span></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="card">
		<h2>输入</h2>
		<form method="post">
			<div class="row" style="margin-bottom:10px;">
				<input type="text" name="text" value="<?php echo _h($text); ?>" placeholder="请输入要分词的文本">
			</div>
			<div class="row" style="margin-bottom:10px;">
				<label>charset
					<select name="charset">
						<option value="utf8" <?php echo $charset === 'utf8' ? 'selected' : ''; ?>>utf8</option>
						<option value="gbk" <?php echo $charset === 'gbk' ? 'selected' : ''; ?>>gbk</option>
						<option value="big5" <?php echo $charset === 'big5' ? 'selected' : ''; ?>>big5</option>
					</select>
				</label>
				<label>multi(0-15)
					<input type="number" name="multi" value="<?php echo (int)$multi; ?>" min="0" max="15">
				</label>
				<label><input type="checkbox" name="ignore" value="1" <?php echo $ignore ? 'checked' : ''; ?>>ignore</label>
				<label><input type="checkbox" name="duality" value="1" <?php echo $duality ? 'checked' : ''; ?>>duality</label>
				<label>topn
					<input type="number" name="topn" value="<?php echo (int)$topn; ?>" min="0" max="50">
				</label>
				<button type="submit">开始分词</button>
			</div>
		</form>
	</div>

	<?php if (!empty($errors)): ?>
		<div class="card err">
			<h2>错误/警告</h2>
			<ul>
				<?php foreach ($errors as $e): ?>
					<li class="mono"><?php echo _h($e); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="card">
		<h2>分词结果</h2>
		<div class="small">词数：<?php echo count($wordList); ?>，去重后：<?php echo count($uniqWords); ?></div>
		<div class="pills" style="margin-top:10px;">
			<?php foreach (array_slice($wordList, 0, 200) as $w): ?>
				<div class="pill"><?php echo _h($w); ?></div>
			<?php endforeach; ?>
		</div>
		<?php if (count($wordList) > 200): ?>
			<div class="small" style="margin-top:8px;">已截断显示前 200 个词。</div>
		<?php endif; ?>
	</div>

	<?php if (is_array($tops) && !empty($tops)): ?>
		<div class="card">
			<h2>Top 词（get_tops）</h2>
			<table>
				<thead>
				<tr>
					<th style="width:60px;">#</th>
					<th>word</th>
					<th style="width:90px;">weight</th>
					<th style="width:90px;">times</th>
					<th style="width:90px;">attr</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($tops as $i => $t): ?>
					<tr>
						<td><?php echo (int)($i + 1); ?></td>
						<td class="mono"><?php echo _h($t['word'] ?? ''); ?></td>
						<td class="mono"><?php echo _h($t['weight'] ?? ''); ?></td>
						<td class="mono"><?php echo _h($t['times'] ?? ''); ?></td>
						<td class="mono"><?php echo _h($t['attr'] ?? ''); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<div class="card">
		<h2>原始 token 明细（get_result 合并后）</h2>
		<?php if (empty($wordsArray)): ?>
			<div class="warn">暂无结果：如果扩展已加载但结果为空，通常是词典未加载成功或输入编码不匹配。</div>
		<?php else: ?>
			<table>
				<thead>
				<tr>
					<th style="width:60px;">#</th>
					<th>word</th>
					<th style="width:70px;">attr</th>
					<th style="width:70px;">off</th>
					<th style="width:70px;">len</th>
					<th style="width:90px;">idf</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($wordsArray as $i => $w): ?>
					<tr>
						<td><?php echo (int)($i + 1); ?></td>
						<td class="mono"><?php echo _h($w['word'] ?? ''); ?></td>
						<td class="mono"><?php echo _h($w['attr'] ?? ''); ?></td>
						<td class="mono"><?php echo _h($w['off'] ?? ($w['offset'] ?? '')); ?></td>
						<td class="mono"><?php echo _h($w['len'] ?? ''); ?></td>
						<td class="mono"><?php echo _h($w['idf'] ?? ''); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="small" style="padding: 6px 2px;">
		执行耗时: <?php echo round((microtime(true) - $GLOBALS['_beginTime']) * 1000, 2); ?>ms
	</div>
</div>
</body>
</html>

