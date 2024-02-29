<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Http\Request;

use Symfony\Component\HttpFoundation\Request as BaseRequest;

class Request
{
	public const FIELD_PAGE = 'pagination';
	public const FIELD_PER_PAGE = 'par-page';

	private BaseRequest $request;

	private int $page = 1;

	public int $perPage = 9;


	public function __construct()
	{
		$this->request = new BaseRequest($_GET, $_POST, [], $_COOKIE, $_FILES, $_SERVER);

		$this->setPage();
		$this->setPerPage();
	}

	public function getParamsByKey(string $key): mixed
	{
		if ($value = $this->request->query->get($key)) {
			return $value;
		}

		return null;
	}

	public function setPage(): self
	{
		if ($page = $this->getParamsByKey(self::FIELD_PAGE)) {
			if (is_numeric($page)) {
				$this->page = (int)$page;
			}
		}

		return $this;
	}

	public function getPage(): int
	{
		return $this->page;
	}

	public function setPerPage(): self
	{
		if ($perPage = $this->getParamsByKey(self::FIELD_PER_PAGE)) {
			if (is_numeric($perPage)) {
				$this->perPage = (int)$perPage;
			}
		}

		return $this;
	}

	/**
	 * @return int
	 */
	public function getPerPage(): int
	{
		return $this->perPage;
	}
}
