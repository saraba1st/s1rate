<?php

class RatingController {

	public function __construct($pageid, $currentUser ) {

	}

	public static function ratePage(Title $page,User $user, $score ) {
        $score = intval($score);
        $item = 'item'.(string)(3 - $score);
        global $wgRateInterval;

		try {

            $resultData = self::getUserLastScore($page, $user);
            if ( time() - $resultData['date'] < $wgRateInterval ){
                return false;
            }
            $dbw = wfGetDB( DB_MASTER );
            $dbw->startAtomic(__METHOD__);

            $dbw->insert(
                's1rate_records',
                [
                    'page_id' => $page->getArticleID(),
                    'user_id' => $user->getId(),
                    'user_name' => $user->getName(),
                    'score' => $score,
                ],
                __METHOD__
            );

            if( !empty($resultData) ){
                $lastScoreItem = 'item'.(3 - $resultData['lastScore']);
                $dbw->update(
                    's1rate_results',
                    [
                        'page_id' => $page->getArticleId(),
                        'title' => $page->getSubpageText(),
                        $lastScoreItem.'='.$lastScoreItem.'-1'
                    ],
                    [
                        'page_id = '.$page->getArticleID()
                    ],
                    __METHOD__
                );
            }

            $dbw->upsert(
                's1rate_results',
                [
                    'page_id' => $page->getArticleId(),
                    'title' => $page->getSubpageText(),
                    $item => 1
                ],
                [ 'page_id' ],
                [
                    $item.' = '.$item.' + 1'
                ],
                __METHOD__
            );

            $dbw->endAtomic( __METHOD__ );
            return true;

		} catch ( Exception $ex ) {
            throw new Exception('DB Error');
		}

	}

	public static function getUserLastScore(Title $page, $user) {
		$ret = array();

		try {
            $dbr = wfGetDB( DB_SLAVE );

            $result = $dbr->selectRow(
                's1rate_records',
                [
                    'page_id',
                    'user_name',
                    'score',
                    'unix_timestamp(date)'
                ],
                [
                    'page_id' => $page->getArticleId(),
                    'user_name' => $user
                ],
                __METHOD__,
                [
                    'ORDER BY' => 'id DESC'
                ]
            );

            if ( $result ) {
                $ret = array(
                    'pageId' => $result->page_id,
                    'userName' => $result->user_name,
                    'lastScore' => $result->score,
                    'date' => $result->{'unix_timestamp(date)'}
                );
            }

			return $ret;
		
		} catch ( Exception $ex ) {
            throw new Exception('DB Error');
		}
	}

    public static function getPageScore(Title $page){
        $ret = array();

        try {
            $dbr = wfGetDB( DB_SLAVE );

            $result = $dbr->selectRow(
                's1rate_results',
                [
                    '*',
                ],
                [
                    'page_id' => $page->getArticleId()
                ],
                __METHOD__
            );

            if ( $result ) {
                $ret = array(
                    'pageId' => $result->page_id,
                    'title' => $result->title,
                    'results' => array(
                        'item1' => $result->item1,
                        'item2' => $result->item2,
                        'item3' => $result->item3,
                        'item4' => $result->item4,
                        'item5' => $result->item5
                    )
                );
            }

            return $ret;

        } catch ( Exception $ex ) {
            throw new Exception('DB Error');
        }
    }

	private function checkRatingContext() {

		if ( !isset( $this->pageid )) {
			return false;
		}

		if ( $this->pageid <= 0 ) {
			return false;
		}

		return true;
	}
}
