import {createClient} from "@supabase/supabase-js";
export const supabase=createClient("https://osjkmigxuzxqtvmrnfhn.supabase.co","sb_publishable_9JREGxsmkpXaXH8H8UvKsg_L41CPsvC",{auth:{persistSession:true,autoRefreshToken:true}});
