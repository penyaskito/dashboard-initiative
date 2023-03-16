ddev drush si --yes
ddev drush upwd admin admin
ddev drush user:role:add administrator admin
ddev drush cset system.site uuid 05104925-b5ef-447d-a96a-bb6b3eed6182 --yes
ddev drush entity-delete shortcut_set --yes
ddev drush cim --yes
# For restoring shortcuts.
ddev drush php-eval 'include_once "core/profiles/standard/standard.install"; standard_install();'
