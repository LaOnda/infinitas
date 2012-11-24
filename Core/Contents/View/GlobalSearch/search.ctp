<?php
	if (empty($this->request->pass[0])) {
		$this->request->pass = array('');
	}
	
	$hilightOptions = array(
		'regex' => "|\b%s\b|iu",
		'format' => '<b class="search">\1</b>'
	);
	
	$results = array();
	foreach ($search as &$s) {
		$s['GlobalContent']['title'] = String::highlight($s['GlobalContent']['title'], $this->request->pass[0], $hilightOptions);
		$s['GlobalContent']['body'] = String::highlight(String::excerpt(strip_tags($s['GlobalContent']['body']), $this->request->pass[0]), $this->request->pass[0], $hilightOptions);
		$s['GlobalContent']['url'] = InfinitasRouter::url($this->GlobalContents->url($s));
		
		$results[] = sprintf(
			'<div class="result"><span class="link">%s</span><span class="url">%s</span><p>%s</p></div>',
			$this->Html->link(String::truncate($s['GlobalContent']['title'], 60), $s['GlobalContent']['url'], array('escape' => false)),
			$s['GlobalContent']['url'],
			$s['GlobalContent']['body']
		);
	}
	
	$search = $this->Form->create(null, array('inputDefaults' => array('label' => false, 'div' => false)));
		$search .= $this->Form->input('search', array('value' => $this->request->pass[0]));
		$search .= $this->Form->input('global_category_id', array('options' => $globalCategories));
		$search .= $this->Form->submit(__d('contents', 'Search'), array('class' => 'submit'));
	$search .= $this->Form->end();
	
	echo sprintf('<div class="search">%s</div>', $search) . implode('', $results);
        echo $this->element('pagination/navigation');