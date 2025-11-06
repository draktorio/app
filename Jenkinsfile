pipeline {
    agent any

    environment {
        SWARM_STACK_NAME = "app"
        DB_SERVICE = 'db'
        DB_USER = 'root'
        DB_PASSWORD = 'secret'
        DB_NAME = 'lena'
        FRONTEND_URL = 'http://192.168.0.1:8080'
        MYSQL_IMAGE = 'local/mysql-lena:latest'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build MySQL Image') {
            steps {
                script {
                    echo "Собираем MySQL образ с дампом..."
                    sh """
                        docker build --no-cache -f mysql.Dockerfile -t ${MYSQL_IMAGE} .
                    """
                }
            }
        }

        stage('Build App Image') {
            steps {
                script {
                    echo "Собираем образ бекенда..."
                    sh "docker build --no-cache -f php.Dockerfile -t draktorio/crudback ."
                }
            }
        }

        stage('Remove Old Stack and Volumes') {
            steps {
                script {
                    echo "Удаляем старый стек и контейнеры..."
                    sh """
                        docker stack rm ${SWARM_STACK_NAME} || true
                        sleep 15

                        echo "Удаляем все тома MySQL..."
                        docker volume ls -q | grep mysql | xargs -r docker volume rm -f

                        echo "Удаляем все контейнеры старого стека..."
                        docker ps -q --filter name=${SWARM_STACK_NAME} | xargs -r docker rm -f || true
                    """
                }
            }
        }

        stage('Deploy to Docker Swarm') {
            steps {
                script {
                    echo "Инициализируем Swarm если нужно..."
                    sh '''
                        if ! docker info | grep -q "Swarm: active"; then
                            docker swarm init || true
                        fi
                    '''

                    echo "Прописываем свежий образ MySQL в docker-compose.yaml..."
                    sh """
                        sed -i 's|image: .*mysql.*|image: ${MYSQL_IMAGE}|g' docker-compose.yaml
                    """

                    echo "Деплой стека..."
                    sh "docker stack deploy --with-registry-auth -c docker-compose.yaml ${SWARM_STACK_NAME}"
                }
            }
        }

        stage('Run Tests') {
            steps {
                script {
                    echo "Ждем запуска сервисов..."
                    sleep 30

                    echo "Проверка доступности фронта..."
                    sh """
                        if ! curl -fsS ${FRONTEND_URL}; then
                            echo 'Front недоступен'
                            exit 1
                        fi
                    """

                    echo "Получение ID контейнера базы данных..."
                    def dbContainerId = sh(
                        script: "docker ps --filter name=${SWARM_STACK_NAME}_${DB_SERVICE} --format '{{.ID}}'",
                        returnStdout: true
                    ).trim()

                    if (!dbContainerId) {
                        error("Контейнер DB не найден")
                    }

                    echo "Ждем готовности базы данных..."
                    sh """
                        timeout 60s bash -c '
                            until docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -e "SELECT 1;" > /dev/null 2>&1; do
                                echo "Ждем базу данных..."
                                sleep 5
                            done
                        '
                    """

                    echo "Проверка типа поля description..."
                    def fieldType = sh(
                        script: """
                            docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -D ${DB_NAME} -N -e \\
                            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='records' AND COLUMN_NAME='description';"
                        """,
                        returnStdout: true
                    ).trim().toLowerCase()

                    echo "Тип поля description: ${fieldType}"

                    if (fieldType == 'varchar') {
                        error("ОШИБКА: Найдено VARCHAR. Ожидается TEXT.")
                    } else if (fieldType == 'text') {
                        echo "УСПЕХ: Поле description имеет правильный тип TEXT."
                    } else {
                        error("НЕИЗВЕСТНЫЙ ТИП: ${fieldType}")
                    }

                    echo "Вывод всех записей для проверки..."
                    sh "docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -D ${DB_NAME} -e 'SELECT * FROM records;'"
                }
            }
        }
    }

    post {
        success {
            echo 'Deployment completed successfully!'
        }
        failure {
            echo 'Deployment failed!'
        }
        always {
            cleanWs()
        }
    }
}

