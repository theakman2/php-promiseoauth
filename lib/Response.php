<?php
class POA_Response {
	
	/**
	 * @var mixed
	 */
	protected $_result = null;
	
	/**
	 * @var integer
	 */
	protected $_code = null;
	
	/**
	 * @var string
	 */
	protected $_contentType = null;
	
	/**
	 * @var DateTime
	 */
	protected $_time = null;
	
	public function getResult() {
		return $this->_result;
	}
	
	public function setResult($result) {
		$this->_result = $result;
		if ($this->_time === null) {
			$this->setTime(new DateTime());
		}
		return $this;
	}
	
	public function getCode() {
		return $this->_code;
	}
	
	public function setCode($code) {
		$this->_code = (int)$code;
		if ($this->_time === null) {
			$this->setTime(new DateTime());
		}
		return $this;
	}
	
	public function getContentType() {
		return $this->_contentType;
	}
	
	public function setContentType($type) {
		$this->_contentType = (string)$type;
		if ($this->_time === null) {
			$this->setTime(new DateTime());
		}
		return $this;
	}
	
	public function getTime() {
		return $this->_time;
	}
	
	public function setTime(DateTime $dateTime) {
		$this->_time = $dateTime;
		return $this;
	}
	
	public function copyFrom(POA_Response $response) {
		if ($response) {
			$this->setTime($response->getTime());
			$this->setResult($response->getResult());
			$this->setCode($response->getCode());
			$this->setContentType($response->getContentType());
		}
		return $this;
	}
	
	public function isSuccess() {
		return (
			$this->_code
			&& ($this->_code >= 200)
			&& ($this->_code < 300)
		);
	}
	
}