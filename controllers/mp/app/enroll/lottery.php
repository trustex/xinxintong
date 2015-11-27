<?php
namespace mp\app\enroll;

require_once dirname(dirname(__FILE__)) . '/base.php';
/**
 *
 */
class lottery extends \mp\app\app_base {
	/**
	 *
	 */
	public function index_action() {
		$this->view_action('/mp/app/enroll/detail');
	}
	/**
	 * 创建抽奖页面
	 *
	 * @param string $aid
	 * @param string $type
	 */
	public function pageCreate_action($aid, $type = 'carousel') {
		$uid = \TMS_CLIENT::get_client_uid();
		$modelCode = $this->model('code/page');
		$code = $modelCode->create($uid);

		$this->model()->update('xxt_enroll', array('lottery_page_id' => $code->id), "id='$aid'");

		$template = dirname(__FILE__) . '/template/lottery/' . $type;
		/*page*/
		$data = array(
			'html' => file_get_contents($template . '.html'),
			'css' => file_get_contents($template . '.css'),
			'js' => file_get_contents($template . '.js'),
		);
		$modelCode->modify($code->id, $data);
		/*config*/
		$config = file_get_contents($template . '.json');
		$config = preg_replace('/\t|\r|\n/', '', $config);
		$config = json_decode($config);
		if (!empty($config->extjs)) {
			foreach ($config->extjs as $js) {
				$modelCode->insert('xxt_code_external', array('code_id' => $code->id, 'type' => 'J', 'url' => $js), false);
			}
		}
		if (!empty($config->extcss)) {
			foreach ($config->extcss as $css) {
				$modelCode->insert('xxt_code_external', array('code_id' => $code->id, 'type' => 'C', 'url' => $css), false);
			}
		}

		return new \ResponseData($code->id);
	}
	/**
	 * 抽奖的轮次
	 */
	public function roundsGet_action($aid) {
		$result = $this->model('app\enroll\lottery')->rounds($aid);

		return new \ResponseData($result);
	}
	/**
	 * 抽奖的轮次
	 */
	public function roundAdd_action($aid) {
		$r = array(
			'aid' => $aid,
			'round_id' => uniqid(),
			'create_at' => time(),
			'title' => '新轮次',
			'targets' => '',
		);
		$this->model()->insert('xxt_enroll_lottery_round', $r, false);

		return new \ResponseData($r);
	}
	/**
	 * 抽奖的轮次
	 *
	 * todo 临时
	 */
	public function roundUpdate_action($aid, $rid) {
		$nv = $this->getPostJson();

		if (isset($nv->targets)) {
			$nv->targets = $this->model()->escape($nv->targets);
		}

		$rst = $this->model()->update(
			'xxt_enroll_lottery_round',
			(array) $nv,
			"aid='$aid' and round_id='$rid'"
		);

		return new \ResponseData($rst);
	}
	/**
	 * 抽奖的轮次
	 *
	 * todo 临时
	 */
	public function roundRemove_action($aid, $rid) {
		/**
		 * 已过已经有抽奖数据不允许删除
		 */
		$q = array(
			'count(*)',
			'xxt_enroll_lottery',
			"aid='$aid' and round_id='$rid'",
		);
		if (0 < (int) $this->model()->query_val_ss($q)) {
			return new \ResponseError('已经有抽奖数据，不允许删除轮次！');
		}

		$rst = $this->model()->delete(
			'xxt_enroll_lottery_round',
			"aid='$aid' and round_id='$rid'"
		);

		return new \ResponseData($rst);
	}
	/**
	 * 中奖的人
	 */
	public function winnersGet_action($aid, $rid = null) {
		$result = $this->model('app\enroll\lottery')->winners($aid, $rid);

		return new \ResponseData($result);
	}
	/**
	 * 清空参与抽奖的人
	 */
	public function empty_action($aid) {
		$rst = $this->model()->delete('xxt_enroll_lottery', "aid='$aid'");

		return new \ResponseData($result);
	}
}