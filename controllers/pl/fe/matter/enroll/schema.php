<?php
namespace pl\fe\matter\enroll;

require_once dirname(__FILE__) . '/main_base.php';
/**
 * 登记活动题目
 */
class schema extends main_base {
	/**
	 * 由目标活动的选择题创建填写题
	 *
	 * @param string $app 需要创建题目的登记活动
	 * @param string $targetApp 数据来源的登记活动
	 *
	 */
	public function inputByOption_action($app, $targetApp, $round = '') {
		if (false === $this->accountUser()) {
			return new \ResponseTimeout();
		}

		$oPosted = $this->getPostJson();
		if (empty($oPosted->schemas)) {
			return new \ParameterError('没有指定题目');
		}

		$modelEnl = $this->model('matter\enroll');

		$oApp = $modelEnl->byId($app, ['fields' => 'siteid,state,mission_id,sync_mission_round', 'appRid' => $round]);
		if (false === $oApp || $oApp->state !== '1') {
			return new \ObjectNotFoundError();
		}

		$oTargetApp = $modelEnl->byId($targetApp, ['fields' => 'siteid,state,mission_id,data_schemas,sync_mission_round']);
		if (false === $oTargetApp || $oTargetApp->state !== '1') {
			return new \ObjectNotFoundError();
		}
		if ($oApp->mission_id !== $oTargetApp->mission_id) {
			return new \ParameterError('仅支持在同一个项目的活动间通过记录生成题目');
		}

		$targetSchemas = []; // 目标应用中选择的题目
		foreach ($oPosted->schemas as $oSchema) {
			foreach ($oTargetApp->dataSchemas as $oSchema2) {
				if ($oSchema->id === $oSchema2->id) {
					$targetSchemas[] = $oSchema2;
					break;
				}
			}
		}
		if (empty($targetSchemas)) {
			return new \ParameterError('指定的题目无效');
		}

		/* 匹配的轮次 */
		$oAssignedRnd = $oApp->appRound;
		if ($oAssignedRnd) {
			$modelRnd = $this->model('matter\enroll\round');
			$oTargetAppRnd = $modelRnd->byMissionRid($oTargetApp, $oAssignedRnd->mission_rid, ['fields' => 'rid,mission_rid']);
		}

		/* 目标活动的统计结果 */
		$modelRec = $this->model('matter\enroll\record');
		$aTargetData = $modelRec->getStat($oTargetApp, !empty($oTargetAppRnd) ? $oTargetAppRnd->rid : '', 'N');
		$newSchemas = []; // 根据记录创建的题目
		$modelDat = $this->model('matter\enroll\data');
		foreach ($targetSchemas as $oTargetSchema) {
			switch ($oTargetSchema->type) {
			case 'single':
			case 'multiple':
				if (!empty($aTargetData[$oTargetSchema->id]->ops)) {
					$options = $aTargetData[$oTargetSchema->id]->ops;
					usort($options, function ($a, $b) {
						return $a->c < $b->c;
					});
					$bGenerated = false;
					if (!empty($oTargetSchema->limit->scope) && !empty($oTargetSchema->limit->num) && (int) $oTargetSchema->limit->num) {
						if ($oTargetSchema->limit->scope === 'top') {
							$this->_genSchemaByTopOptions($oTargetSchema, $options, $oTargetSchema->limit->num, $newSchemas);
							$bGenerated = true;
						} else if ($oTargetSchema->limit->scope === 'checked') {
							$this->_genSchemaByCheckedOptions($oTargetSchema, $options, $oTargetSchema->limit->num, $newSchemas);
							$bGenerated = true;
						}
					}
					if (!$bGenerated) {
						if (!empty($oPosted->limit->scope) && !empty($oPosted->limit->num) && (int) $oPosted->limit->num) {
							if ($oPosted->limit->scope === 'top') {
								$this->_genSchemaByTopOptions($oTargetSchema, $options, $oPosted->limit->num, $newSchemas);
								$bGenerated = true;
							} else if ($oPosted->limit->scope === 'checked') {
								$this->_genSchemaByCheckedOptions($oTargetSchema, $options, $oPosted->limit->num, $newSchemas);
								$bGenerated = true;
							}
						}
					}
					if (!$bGenerated) {
						$this->_genSchemaByTopOptions($oTargetSchema, $options, count($options), $newSchemas);
					}
				}
				break;
			}
		}

		return new \ResponseData($newSchemas);
	}
	/**
	 * 根据指定的数量，从选项生成题目
	 */
	private function _genSchemaByTopOptions($oTargetSchema, $votingOptions, $limitNum, &$newSchemas) {
		if ($limitNum > count($votingOptions)) {
			$limitNum = count($votingOptions);
		}

		$originalOptionsByValue = [];
		foreach ($oTargetSchema->ops as $oOption) {
			$originalOptionsByValue[$oOption->v] = $oOption;
		}

		for ($i = 0; $i < $limitNum; $i++) {
			$oOption = $votingOptions[$i];
			if (isset($originalOptionsByValue[$oOption->v])) {
				$oNewSchema = new \stdClass;
				$oNewSchema->id = $oTargetSchema->id . $oOption->v;
				$oNewSchema->title = $oOption->l;
				$oNewSchema->type = 'longtext';
				if (isset($originalOptionsByValue[$oOption->v]->ds)) {
					$oNewSchema->ds = $originalOptionsByValue[$oOption->v]->ds;
				}
				$newSchemas[] = $oNewSchema;
			}
		}
	}
	/**
	 * 根据选项获得的选择数量生成题目
	 */
	private function _genSchemaByCheckedOptions($oTargetSchema, $votingOptions, $checkedNum, &$newSchemas) {
		for ($i = 0, $ii = count($votingOptions); $i < $ii; $i++) {
			$oOption = $votingOptions[$i];

			$originalOptionsByValue = [];
			foreach ($oTargetSchema->ops as $oOption) {
				$originalOptionsByValue[$oOption->v] = $oOption;
			}

			if (isset($originalOptionsByValue[$oOption->v])) {
				if ($oOption->c < $checkedNum) {
					break;
				}
				$oNewSchema = new \stdClass;
				$oNewSchema->id = $oTargetSchema->id . $oOption->v;
				$oNewSchema->title = $oOption->l;
				$oNewSchema->type = 'longtext';
				if (isset($originalOptionsByValue[$oOption->v]->ds)) {
					$oNewSchema->ds = $originalOptionsByValue[$oOption->v]->ds;
				}
				$newSchemas[] = $oNewSchema;
			}
		}
	}
	/**
	 * 由目标活动的填写题创建打分题
	 *
	 * @param string $app 需要创建题目的登记活动
	 * @param string $targetApp 数据来源的登记活动
	 *
	 */
	public function scoreByInput_action($app, $targetApp, $round = '') {
		if (false === $this->accountUser()) {
			return new \ResponseTimeout();
		}

		$oPosted = $this->getPostJson();
		if (empty($oPosted->schemas)) {
			return new \ParameterError('没有指定题目');
		}
		if (empty($oPosted->proto->range) || empty($oPosted->proto->ops) || !is_array($oPosted->proto->ops)) {
			return new \ParameterError('没有指定题目的原型，或原型不完整');
		}
		if (count($oPosted->proto->range) !== 2 || !is_int((int) $oPosted->proto->range[0]) || !is_int((int) $oPosted->proto->range[1])) {
			return new \ParameterError('题目原型中的【range】参数格式不正确');
		}
		foreach ($oPosted->proto->ops as $oScoreOption) {
			if (empty($oScoreOption->v) || empty($oScoreOption->l)) {
				return new \ParameterError('题目原型中的【ops】参数格式不正确');
			}
		}

		$modelEnl = $this->model('matter\enroll');

		$oApp = $modelEnl->byId($app, ['fields' => 'siteid,state,mission_id,sync_mission_round', 'appRid' => $round]);
		if (false === $oApp || $oApp->state !== '1') {
			return new \ObjectNotFoundError();
		}

		$oTargetApp = $modelEnl->byId($targetApp, ['fields' => 'siteid,state,mission_id,data_schemas,sync_mission_round']);
		if (false === $oTargetApp || $oTargetApp->state !== '1') {
			return new \ObjectNotFoundError();
		}
		if ($oApp->mission_id !== $oTargetApp->mission_id) {
			return new \ParameterError('仅支持在同一个项目的活动间通过记录生成题目');
		}

		$targetSchemas = []; // 目标应用中选择的题目
		foreach ($oPosted->schemas as $oSchema) {
			foreach ($oTargetApp->dataSchemas as $oSchema2) {
				if ($oSchema->id === $oSchema2->id && in_array($oSchema2->type, ['shorttext', 'longtext'])) {
					$targetSchemas[] = $oSchema2;
					break;
				}
			}
		}
		if (empty($targetSchemas)) {
			return new \ParameterError('目标活动中指定的题目无效');
		}
		$oAssignedRnd = $oApp->appRound;
		if ($oAssignedRnd) {
			$modelRnd = $this->model('matter\enroll\round');
			$oTargetAppRnd = $modelRnd->byMissionRid($oTargetApp, $oAssignedRnd->mission_rid, ['fields' => 'rid,mission_rid']);
		}

		/* 目标活动的统计结果 */
		$modelRec = $this->model('matter\enroll\record');
		$oOptions = [];
		if (isset($oTargetAppRnd->rid)) {
			$oOptions['record'] = (object) ['rid' => $oTargetAppRnd->rid];
		}
		$aSearchResult = $modelRec->byApp($oTargetApp, $oOptions);
		$aRecords = $aSearchResult->records;
		if (empty($aRecords)) {
			return new \ParameterError('目标活动中没有填写记录');
		}
		$newSchemas = []; // 根据记录创建的题目
		foreach ($targetSchemas as $oTargetSchema) {
			foreach ($aRecords as $oTargetRecord) {
				if (empty($oTargetRecord->data->{$oTargetSchema->id})) {
					continue;
				}
				/* 新题目 */
				$oNewSchema = new \stdClass;
				$oNewSchema->id = 's' . $oTargetRecord->id;
				$oNewSchema->title = $oTargetRecord->data->{$oTargetSchema->id};
				$oNewSchema->type = 'score';
				$oNewSchema->required = 'Y';
				$oNewSchema->range = $oPosted->proto->range;
				$oNewSchema->ops = $oPosted->proto->ops;
				if (!empty($oPosted->proto->requireScore)) {
					$oNewSchema->requireScore = 'Y';
					$oNewSchema->scoreMode = 'evaluation';
				}
				/* 记录数据来源 */
				$oNewSchema->ds = (object) ['ek' => $oTargetRecord->enroll_key, 'userid' => $oTargetRecord->userid, 'nickname' => $oTargetRecord->nickname];
				$newSchemas[] = $oNewSchema;
			}
		}

		return new \ResponseData($newSchemas);
	}
}