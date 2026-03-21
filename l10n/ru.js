OC.L10N.register(
    "auto_groups",
    {
    "Group \"%1$s\" is used in the Auto Groups App and cannot be deleted." : "Группа «%1$s» не может быть удалена, так как используется в приложении «Автогруппы»",
    "Auto Groups" : "Автогруппы",
    "Automatically adds all users to one or multiple specified groups." : "Автоматическое добавление всех пользователей в одну или несколько заданных групп",
    "Automatically add all users to these groups." : "Автоматически добавлять всех пользователей в эти группы",
    "Override Groups:" : "Переопределяющие группы:",
    "Users which are member of at least one of these groups are removed from the auto groups. This is also the case if the user is added to one of these groups after creation, i.e., membership in the override groups is checked after each group modification." : "Пользователи, входящие хотя бы в одну из перечисленных групп, будут удалены их автогрупп. Удаление из автогрупп будет выполнено также в случае, если пользователь был добавлен в эти группы после их создания. Участие пользователя в группах из списка переопределяющих групп проверяется после каждого изменения таких групп. ",
    "Set Auto Group membership on user creation." : "При создании пользователя включать его в Автогруппу",
    "If checked, Auto Group membership will be enforced on user creation." : "Если отмечено, то при создании пользователя он будет добавлен в Автогруппу.",
    "Check Auto Group membership on modification of a user's groups." : "Проверяйте членство в Auto Group при изменении групп пользователя.",
    "If checked, Auto Group membership will be re-enforced for a user account every time it is added to or removed from a group." : "Если этот флажок установлен, членство в автоматической группе будет принудительно применяться для учетной записи пользователя каждый раз, когда она добавляется в группу или удаляется из нее.",
    "Check for correct Auto Group membership on every login." : "Проверять принадлежность пользователя автогруппе при каждом входе в систему",
    "Enable this setting to enforce proper Auto Group membership on every successful login. This is useful if either users are not created in Nextcloud (e.g., with external user backends) or to enforce correct group membership for all users when the Auto Groups / Override Groups have changed." : "При включении этого параметра будет проверяться действительность принадлежности пользователя автогруппе при его каждом успешном  входе в систему. Такое поведение полезно в случае, когда учётные записи пользователей создаются вне Nextcloud (к примеру, при использовании механизмов поддержки внешнего управления пользователями) или для принудительного изменения участия пользователей в группах при изменении списка автоматически назначаемых или  переопределяющих групп."
},
"nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : n%10==0 || (n%10>=5 && n%10<=9) || (n%100>=11 && n%100<=14)? 2 : 3);");
