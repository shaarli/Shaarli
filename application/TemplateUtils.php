<?php
/**
 * Invokes RainTPL to render a template
 *
 * @param PageContext $pageContext the context for the rendered page
 * @param string      $pageName    the name of the page to render
 */
function render_page($pageContext, $pageName)
{
    $template = new RainTPL();
    $template->assign($pageContext->getData());
    $template->draw($pageName);
}

