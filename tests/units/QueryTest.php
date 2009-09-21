<?php

class Unit_QueryTest extends OutletTestCase {
	public function testFluentInterface() {
		$query = new OutletQuery();

		$result = $query->from('Entity')
			->where('where')
			->orderBy('order')
			->offset(3)
			->limit(1)
			->leftJoin('left')
			->innerJoin('join')
			->with('OtherEntity')
			->groupBy('group')
			->having('having')
			->select('select');

		$this->assertThat($result, $this->isInstanceOf('OutletQuery'));
	}
}