#!/bin/bash

DB_USER="bassel"
DB_PASS="bassel_password"
DB_NAME="radius"
DB_HOST="172.30.16.200"
LNS_IP="10.150.50.2"
COA_SECRET="sama@123"
REALM="samawifi.sy"

#a استعلام المستخدمين ضمن مجموعات _2x فقط
mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
SELECT username, groupname
FROM radusergroup
WHERE groupname LIKE 'speed_%_2x'
" | while read username double_group; do

    #a استخراج اسم المجموعة الأصلية بإزالة _2x
    original_group="${double_group%_2x}"

    #a التأكد من أن مجموعة الأصلية موجودة في radgroupreply
    group_exists=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
        SELECT 1 FROM radgroupreply WHERE groupname = '$original_group' LIMIT 1;
    ")

    if [ "$group_exists" == "1" ]; then
        echo "🔁 Restoring $username to $original_group"

        #a حذف المستخدم من المجموعة _2x
        mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
            DELETE FROM radusergroup
            WHERE username = '$username' AND groupname = '$double_group';
        "

        #a جلب Mikrotik-Address-List من المجموعة الأصلية
        address_list=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
            SELECT value FROM radgroupreply 
            WHERE groupname = '$original_group' AND attribute = 'Mikrotik-Address-List' 
            LIMIT 1;
        ")

        if [ -n "$address_list" ]; then
            #a التحقق مما إذا كان المستخدم متصلاً
            is_online=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
                SELECT COUNT(*) FROM radacct WHERE username = '$username' AND acctstoptime IS NULL;
            ")

            if [ "$is_online" -ge 1 ]; then
                #a إرسال CoA لإرجاع السرعة الأصلية
                echo "User-Name = '$username@$REALM', Mikrotik-Address-List := '$address_list'" | \
                radclient -x $LNS_IP:3799 coa "$COA_SECRET"

                echo "✅ CoA sent to $username to restore Mikrotik-Address-List = $address_list"
            else
                echo "ℹ️ $username is not online. No CoA sent."
            fi
        else
            echo "⚠️ No Mikrotik-Address-List found for $original_group. Skipping CoA."
        fi
    else
        echo "❌ Original group $original_group does not exist. Skipping $username."
    fi

done
