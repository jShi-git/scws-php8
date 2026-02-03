dnl $Id$
dnl config.m4 for extension scws

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

PHP_ARG_WITH(scws, for scws support,
[  --with-scws[=DIR]         Include scws support. DIR is the install directory of scws.
                          If not specified it will automatically search the system path.], yes)

if test "$PHP_SCWS" != "no"; then
  if test "$PHP_SCWS" != "built-in"; then
    if test "$PHP_SCWS" != "yes"; then
      SEARCH_PATH="$PHP_SCWS"
    else
      SEARCH_PATH="/usr/local /usr /opt/local /usr/local/scws"    
    fi
    SEARCH_FOR="/include/scws/scws.h"
    SCWS_DIR=""

    # search default path list
    AC_MSG_CHECKING([for scws.h])
    for i in $SEARCH_PATH ; do
      if test -f $i/$SEARCH_FOR; then
        SCWS_DIR=$i
        AC_MSG_RESULT([yes, found in $i])
      fi
    done
    if test -z "$SCWS_DIR"; then
      AC_MSG_RESULT([no])                  
      AC_MSG_ERROR([Please download and install scws from http://www.xunsearch.com/scws])
    fi

    PHP_ADD_INCLUDE($SCWS_DIR/include/scws)
    LIBNAME=scws
    LIBSYMBOL=scws_new

    PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
    [
        PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $SCWS_DIR/lib, SCWS_SHARED_LIBADD)
        AC_DEFINE(HAVE_SCWS,1,[ ])
    ],[
        AC_MSG_ERROR([Incorrect scws library])
    ],[
        -L$SCWS_DIR/lib -lm
    ])

    PHP_SUBST(SCWS_SHARED_LIBADD)
    PHP_NEW_EXTENSION(scws, php_scws.c, $ext_shared)
  else
    dnl # use bundled library
    dnl Build against bundled libscws sources under phpext/libscws.
    dnl If your repository keeps libscws/ at project root, copy it into phpext/ first:
    dnl   cp -R ../libscws ./libscws
    PHP_SCWS_CFLAGS="-I@ext_srcdir@ -I@ext_srcdir@/libscws"
    
    dnl /// jShi-git.260202: add libscws/crc32.c so scws.so exports scws_crc32
    libscws_src="libscws/charset.c libscws/darray.c \
		 libscws/pool.c libscws/rule.c \
		 libscws/scws.c libscws/xdb.c libscws/lock.c \
		 libscws/crc32.c \
		 libscws/xdict.c libscws/xtree.c"
		 
    dnl # check -lm (math lib)
    AC_CHECK_LIB(m, expf, [ PHP_ADD_LIBRARY(m,,SCWS_SHARED_LIBADD) ],
                 [ AC_MSG_ERROR(scws: expf() not supported by this platform) ])
    PHP_SUBST(SCWS_SHARED_LIBADD)
    
    PHP_NEW_EXTENSION(scws, php_scws.c $libscws_src, $ext_shared,,$PHP_SCWS_CFLAGS)
    PHP_ADD_BUILD_DIR($ext_builddir/libscws)
    
    dnl # check unix header files
    AC_CHECK_HEADERS([ sys/file.h sys/time.h unistd.h string.h fcntl.h ],, [ AC_MSG_ERROR(scws: some header file not found) ])

    dnl /// jShi-git.260202: Linux flock / struct flock for lock.c
    dnl # lock support (fix "no proper flock supported" warning on Linux)
    dnl check for flock(2)
    AC_CHECK_FUNCS([flock])
    dnl check for struct flock (fcntl locking)
    AC_CACHE_CHECK([for struct flock], [ac_cv_struct_flock], [
      AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <unistd.h>
#include <fcntl.h>
      ]], [[
struct flock x;
(void)x;
      ]])], [ac_cv_struct_flock=yes], [ac_cv_struct_flock=no])
    ])
    if test "$ac_cv_struct_flock" = "yes"; then
      AC_DEFINE([HAVE_STRUCT_FLOCK], [1], [whether you have struct flock])
    fi
  fi
fi

