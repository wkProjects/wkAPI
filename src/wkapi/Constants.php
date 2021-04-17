<?php

namespace wkprojects\wkapi;

abstract class Constants {
    const USER_NOT_FOUND = 0;
    const USER_CREDENTIALS_INCORRECT = 1;
    const USER_CREDENTIALS_CORRECT = 2;
    const USER_CREDENTIALS_CORRECT_AND_LOGGED_IN = 3;
}