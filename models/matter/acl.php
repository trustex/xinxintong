<?php
namespace matter;

require_once dirname(__FILE__) . '/app_base.php';
/**
 *
 */
class acl_model extends app_base {
	/**
	 * 获得信息墙的所有用户
	 * 将ACL翻译为具体的用户
	 */
	public function wallUsers($siteId, $wid) {
		$users = array();
		/**
		 * 直接指定
		 */
		$q = array(
			'a.identity',
			'xxt_matter_acl a',
			"a.mpid='$siteId' and a.matter_type= 'wall' and a.matter_id='$wid' and idsrc=''",
		);
		if ($acls = $this->query_objs_ss($q)) {
			foreach ($acls as $acl) {
				$users[] = $acl->identity;
			}

		}

		return $users;
	}
	/**
	 * 获得素材的ACL
	 *
	 * 需要根据指定的授权对象的不同获取不同的数据
	 */
	public function byMatter($siteId, $type, $id) {
		$q = array(
			'a.id,a.identity,a.idsrc,a.label',
			'xxt_matter_acl a',
			"a.mpid='$siteId' and a.matter_type='$type' and a.matter_id='$id'",
		);
		$acls = $this->query_objs_ss($q);

		return $acls;
	}
	/**
	 * 通用的白名单检查机制
	 *
	 * $siteId
	 * $table 访问控制列表
	 * $whichAcl 需要检查的列表项
	 * $identity 用户身份标识
	 * $authapis
	 */
	public function canAccess2($siteId, $table, $whichAcl, $identity, $aSchemaIds, $mustInclude = false) {
		/**
		 * 是否设置了白名单
		 */
		if (!$mustInclude) {
			$q = array(
				'count(*)',
				$table,
				$whichAcl,
			);
			if (0 === (int) $this->query_val_ss($q)) {
				return true;
			}

		}
		/**
		 * 检查当前用户是否在白名单中
		 * 如果有多个认证身份信息，有一个在白名单中就行
		 * todo 用户身份必须和指定认证接口匹配才可以
		 */
		$q = array(
			'count(*)',
			$table,
			"$whichAcl and idsrc='' and identity='$identity'",
		);
		if (0 < (int) $this->query_val_ss($q)) {
			return true;
		}

		/**
		 * 后缀匹配，例如：域名匹配
		 */
		$q = array(
			'count(*)',
			$table,
			"$whichAcl and idsrc='' and '$identity' like concat('%',identity)",
		);
		if (1 === ((int) $this->query_val_ss($q))) {
			return true;
		}

		/**
		 * 由认证接口进行检查
		 */
		$q = array(
			'identity,idsrc',
			$table,
			"$whichAcl",
		);
		$acls = $this->query_objs_ss($q);
		if (true === $this->_checkBySchema($siteId, $aSchemaIds, $acls, $identity)) {
			return true;
		}

		return false;
	}
	/**
	 *
	 */
	private function _checkBySchema($siteId, &$aSchemaIds, $acls, $identity) {
		$posted = json_encode($acls);
		$aSchemaIds = explode(',', $aSchemaIds);
		foreach ($aSchemaIds as $schemaId) {
			$q = array(
				'url',
				'xxt_site_member_schema',
				"id=$schemaId and valid='Y'",
			);
			if ($url = $this->query_val_ss($q)) {
				false === strpos($url, 'http') && $url = 'http://' . APP_HTTP_HOST . $url;
				$url .= "/checkAcl?site=$siteId&schema=$schemaId&uid=$identity";
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $posted);
				if (false === ($response = curl_exec($ch))) {
					$err = curl_error($ch);
					curl_close($ch);
					return array(false, $err);
				}
				curl_close($ch);
				$rst = json_decode($response);
				if ($rst->err_code == 0 && $rst->data === 'passed') {
					return true;
				}
			}
		}
		return false;
	}
}