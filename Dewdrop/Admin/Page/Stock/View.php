<?php

namespace Dewdrop\Admin\Page\Stock;

use Dewdrop\Admin\Page\PageAbstract;

class View extends PageAbstract
{
    public function render()
    {
        $this->component->getPermissions()->haltIfNotAllowed('view');

        $listing = $this->component->getListing();
        $id      = $this->request->getQuery($listing->getPrimaryKey()->getName());
        $fields  = $this->component->getFields()->getVisibleFields();
        $data    = $this->component->getListing()->fetchRow($fields, $id);

        $primaryKey = $this->component->getPrimaryModel()->getPrimaryKey();
        $params     = array();

        foreach ($primaryKey as $field) {
            $params[$field] = $this->request->getQuery($field);
        }

        $this->view->params         = $params;
        $this->view->fields         = $fields;
        $this->view->singularTitle  = $this->component->getPrimaryModel()->getSingularTitle();
        $this->view->data           = $data;
        $this->view->id             = $id;
        $this->view->groupingFilter = $this->component->getFieldGroupsFilter();
        $this->view->permissions    = $this->component->getPermissions();

        if ($this->component->getRequest()->isAjax()) {
            $this->component->setShouldRenderLayout(false);
        }
    }
}