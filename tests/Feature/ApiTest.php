<?php

test('Get API version', function () {
  expect(Api::getVersion())
      ->toBeString();
});
